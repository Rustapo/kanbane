/**
 * Board Module
 * Handles board rendering, column management, and card display
 */

const Board = {
    currentBoard: null,
    currentRevision: 0,

    async loadBoard(boardId) {
        try {
            const response = await Api.get(`/api/board.php?board_id=${boardId}&action=get`);
            this.currentBoard = response.data;
            this.currentRevision = response.data.revision;
            this.render();
            return response.data;
        } catch (error) {
            console.error('Failed to load board:', error);
            throw error;
        }
    },

    render() {
        if (!this.currentBoard) return;

        const container = document.getElementById('board-container');
        if (!container) return;

        // Render header
        document.getElementById('board-title').textContent = this.currentBoard.name;

        // Render columns
        const columnsContainer = document.getElementById('columns-container');
        columnsContainer.innerHTML = '';

        const sortedColumns = [...this.currentBoard.columns]
            .filter(col => col.status === 'active')
            .sort((a, b) => a.position - b.position);

        sortedColumns.forEach(column => {
            columnsContainer.appendChild(this.createColumnElement(column));
        });
    },

    createColumnElement(column) {
        const colEl = document.createElement('div');
        colEl.className = 'column';
        colEl.dataset.columnId = column.id;

        const activeCards = column.cards.filter(card => card.status === 'active');

        colEl.innerHTML = `
            <div class="column-header">
                <span class="column-title">${this.escapeHtml(column.title)}</span>
                <span class="card-count">${activeCards.length}</span>
            </div>
            <div class="column-cards" data-column-id="${column.id}">
                ${activeCards.map(card => this.createCardElement(card)).join('')}
            </div>
            <button class="add-card-btn" onclick="Board.showAddCard('${column.id}')">+ Add card</button>
        `;

        return colEl;
    },

    createCardElement(card) {
        const priorityClass = `priority-${card.priority || 'medium'}`;
        const isOverdue = card.due_date && new Date(card.due_date) < new Date() && card.status === 'active';
        
        return `
            <div class="card ${priorityClass}" 
                 data-card-id="${card.id}" 
                 draggable="true"
                 onclick="Board.openCard('${card.id}')">
                <div class="card-title">${this.escapeHtml(card.title)}</div>
                ${card.tags && card.tags.length > 0 ? `
                    <div class="card-tags">
                        ${card.tags.slice(0, 3).map(tagId => `<span class="tag-mini">${tagId}</span>`).join('')}
                    </div>
                ` : ''}
                <div class="card-meta">
                    ${card.due_date ? `
                        <span class="due-date ${isOverdue ? 'overdue' : ''}">
                            ${new Date(card.due_date).toLocaleDateString()}
                        </span>
                    ` : ''}
                    ${card.assignees && card.assignees.length > 0 ? `
                        <span class="assignees-count">${card.assignees.length}</span>
                    ` : ''}
                </div>
            </div>
        `;
    },

    showAddCard(columnId) {
        const title = prompt('Enter card title:');
        if (title && title.trim()) {
            Api.post('/api/card.php', {
                action: 'create',
                board_id: this.currentBoard.id,
                column_id: columnId,
                title: title.trim()
            }).then(() => {
                this.loadBoard(this.currentBoard.id);
            }).catch(err => {
                alert('Failed to create card: ' + err.message);
            });
        }
    },

    openCard(cardId) {
        // Open card modal - implemented in modal.js
        Modal.openCard(cardId);
    },

    async refresh() {
        try {
            const response = await Api.get(`/api/board.php?board_id=${this.currentBoard.id}&action=revision`);
            if (response.changed && response.revision !== this.currentRevision) {
                await this.loadBoard(this.currentBoard.id);
                return true;
            }
            return false;
        } catch (error) {
            console.error('Refresh failed:', error);
            return false;
        }
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};
