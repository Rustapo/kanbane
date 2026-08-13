/**
 * Modal Module
 * Handles card modal dialogs using native <dialog>
 */

const Modal = {
    dialog: null,
    currentCardId: null,
    currentBoardId: null,

    init() {
        // Create dialog element if not exists
        this.dialog = document.getElementById('card-modal');
        if (!this.dialog) {
            this.dialog = document.createElement('dialog');
            this.dialog.id = 'card-modal';
            this.dialog.className = 'modal';
            this.dialog.innerHTML = this.getModalHTML();
            document.body.appendChild(this.dialog);
        }

        // Close handlers
        this.dialog.addEventListener('close', () => this.onClose());
        
        // Save button handler
        document.getElementById('modal-save')?.addEventListener('click', () => this.save());
        document.getElementById('modal-cancel')?.addEventListener('click', () => this.close());
        document.getElementById('modal-delete')?.addEventListener('click', () => this.deleteCard());
        document.getElementById('modal-archive')?.addEventListener('click', () => this.archiveCard());
    },

    getModalHTML() {
        return `
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modal-title">Card</h2>
                    <button class="modal-close" onclick="Modal.close()">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="text" id="card-title" class="form-input" placeholder="Title">
                    <textarea id="card-description" class="form-textarea" placeholder="Description (Markdown supported)" rows="5"></textarea>
                    
                    <div class="form-row">
                        <label>Priority:</label>
                        <select id="card-priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label>Due Date:</label>
                        <input type="date" id="card-due-date" class="form-input">
                    </div>
                    
                    <div class="form-row">
                        <label>Tags:</label>
                        <div id="card-tags" class="tags-container"></div>
                        <button type="button" class="btn-small" onclick="Modal.addTag()">+ Tag</button>
                    </div>
                    
                    <div class="form-row">
                        <label>Assignees:</label>
                        <div id="card-assignees" class="assignees-container"></div>
                    </div>
                    
                    <div class="section">
                        <h3>Checklist</h3>
                        <div id="card-checklists"></div>
                        <button type="button" class="btn-small" onclick="Modal.addChecklist()">+ Add Checklist</button>
                    </div>
                    
                    <div class="section">
                        <h3>Comments</h3>
                        <div id="card-comments"></div>
                        <div class="comment-form">
                            <textarea id="new-comment" placeholder="Add a comment..." rows="2"></textarea>
                            <button type="button" class="btn-small" onclick="Modal.addComment()">Post</button>
                        </div>
                    </div>
                    
                    <div class="card-meta-info">
                        <small>Created: <span id="card-created"></span></small>
                        <small>Updated: <span id="card-updated"></span></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="modal-save" class="btn btn-primary">Save</button>
                    <button id="modal-archive" class="btn btn-warning">Archive</button>
                    <button id="modal-delete" class="btn btn-danger">Delete</button>
                    <button id="modal-cancel" class="btn">Cancel</button>
                </div>
            </div>
        `;
    },

    async openCard(cardId) {
        this.currentCardId = cardId;
        this.currentBoardId = Board.currentBoard.id;
        
        try {
            const response = await Api.get(`/api/card.php?board_id=${this.currentBoardId}&card_id=${cardId}&action=get`);
            const card = response.data;
            
            document.getElementById('modal-title').textContent = card.title || 'Card';
            document.getElementById('card-title').value = card.title || '';
            document.getElementById('card-description').value = card.description || '';
            document.getElementById('card-priority').value = card.priority || 'medium';
            document.getElementById('card-due-date').value = card.due_date || '';
            document.getElementById('card-created').textContent = card.created_at ? new Date(card.created_at).toLocaleString() : '-';
            document.getElementById('card-updated').textContent = card.updated_at ? new Date(card.updated_at).toLocaleString() : '-';
            
            this.renderTags(card.tags || []);
            this.renderAssignees(card.assignees || []);
            this.renderChecklists(card.checklists || []);
            this.renderComments(card.comments || []);
            
            this.dialog.showModal();
        } catch (error) {
            alert('Failed to load card: ' + error.message);
        }
    },

    close() {
        this.dialog.close();
    },

    onClose() {
        this.currentCardId = null;
        this.currentBoardId = null;
    },

    async save() {
        if (!this.currentCardId) return;
        
        const updates = {
            title: document.getElementById('card-title').value.trim(),
            description: document.getElementById('card-description').value.trim(),
            priority: document.getElementById('card-priority').value,
            due_date: document.getElementById('card-due-date').value || null
        };

        try {
            await Api.post('/api/card.php', {
                action: 'update',
                board_id: this.currentBoardId,
                card_id: this.currentCardId,
                ...updates
            });
            
            this.close();
            await Board.loadBoard(this.currentBoardId);
        } catch (error) {
            alert('Failed to save: ' + error.message);
        }
    },

    async archiveCard() {
        if (!this.currentCardId) return;
        
        if (!confirm('Archive this card?')) return;

        try {
            await Api.post('/api/card.php', {
                action: 'archive',
                board_id: this.currentBoardId,
                card_id: this.currentCardId
            });
            
            this.close();
            await Board.loadBoard(this.currentBoardId);
        } catch (error) {
            alert('Failed to archive: ' + error.message);
        }
    },

    async deleteCard() {
        if (!this.currentCardId) return;
        
        if (!confirm('Delete this card permanently? This cannot be undone.')) return;

        try {
            await Api.post('/api/card.php', {
                action: 'delete',
                board_id: this.currentBoardId,
                card_id: this.currentCardId
            });
            
            this.close();
            await Board.loadBoard(this.currentBoardId);
        } catch (error) {
            alert('Failed to delete: ' + error.message);
        }
    },

    renderTags(tags) {
        const container = document.getElementById('card-tags');
        container.innerHTML = tags.map(tagId => 
            `<span class="tag">${tagId} <button onclick="Modal.removeTag('${tagId}')">&times;</button></span>`
        ).join('') || '<em>No tags</em>';
    },

    renderAssignees(assignees) {
        const container = document.getElementById('card-assignees');
        container.innerHTML = assignees.map(userId => 
            `<span class="assignee">${userId}</span>`
        ).join('') || '<em>No assignees</em>';
    },

    renderChecklists(checklists) {
        const container = document.getElementById('card-checklists');
        if (!checklists.length) {
            container.innerHTML = '<em>No checklists</em>';
            return;
        }
        
        container.innerHTML = checklists.map(chk => `
            <div class="checklist">
                <strong>${chk.title}</strong>
                <ul>
                    ${chk.items.map(item => `
                        <li class="${item.completed ? 'completed' : ''}">
                            <input type="checkbox" ${item.completed ? 'checked' : ''} 
                                   onchange="Modal.toggleChecklistItem('${chk.id}', '${item.id}')">
                            ${item.text}
                        </li>
                    `).join('')}
                </ul>
            </div>
        `).join('');
    },

    renderComments(comments) {
        const container = document.getElementById('card-comments');
        if (!comments.length) {
            container.innerHTML = '<em>No comments yet</em>';
            return;
        }
        
        container.innerHTML = comments.map(cmt => `
            <div class="comment">
                <small><strong>${cmt.author_id}</strong> - ${new Date(cmt.created_at).toLocaleString()}</small>
                <p>${Markdown.parse(cmt.text)}</p>
            </div>
        `).join('');
    },

    addTag() {
        const tag = prompt('Enter tag ID:');
        if (tag) {
            // Implementation would call API to add tag
            alert('Tag feature coming soon');
        }
    },

    removeTag(tagId) {
        // Implementation would call API to remove tag
        alert('Remove tag: ' + tagId);
    },

    addChecklist() {
        const title = prompt('Checklist title:');
        if (title) {
            alert('Add checklist feature coming soon');
        }
    },

    toggleChecklistItem(checklistId, itemId) {
        // Implementation would call API to toggle item
        alert('Toggle item: ' + itemId);
    },

    async addComment() {
        const text = document.getElementById('new-comment').value.trim();
        if (!text) return;

        try {
            await Api.post('/api/comment.php', {
                action: 'create',
                board_id: this.currentBoardId,
                card_id: this.currentCardId,
                text: text
            });
            
            document.getElementById('new-comment').value = '';
            await this.openCard(this.currentCardId); // Refresh
        } catch (error) {
            alert('Failed to add comment: ' + error.message);
        }
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => Modal.init());
