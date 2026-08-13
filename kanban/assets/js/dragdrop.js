/**
 * Drag & Drop Module
 * Native HTML5 Drag & Drop for cards and columns
 */

const DragDrop = {
    draggedElement: null,
    draggedType: null, // 'card' or 'column'
    sourceColumn: null,

    init() {
        document.addEventListener('dragstart', (e) => this.handleDragStart(e));
        document.addEventListener('dragend', (e) => this.handleDragEnd(e));
        document.addEventListener('dragover', (e) => this.handleDragOver(e));
        document.addEventListener('drop', (e) => this.handleDrop(e));
    },

    handleDragStart(e) {
        const card = e.target.closest('.card');
        const column = e.target.closest('.column');

        if (card) {
            this.draggedElement = card;
            this.draggedType = 'card';
            this.sourceColumn = card.closest('.column');
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', card.dataset.cardId);
        } else if (column && e.target.classList.contains('column-header')) {
            this.draggedElement = column;
            this.draggedType = 'column';
            column.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', column.dataset.columnId);
        }
    },

    handleDragEnd(e) {
        if (this.draggedElement) {
            this.draggedElement.classList.remove('dragging');
        }
        document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
        this.draggedElement = null;
        this.draggedType = null;
        this.sourceColumn = null;
    },

    handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        const dropTarget = e.target.closest('.card, .column-cards, .column');
        if (dropTarget) {
            document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
            if (this.draggedType === 'card' && (dropTarget.classList.contains('card') || dropTarget.classList.contains('column-cards'))) {
                dropTarget.classList.add('drag-over');
            } else if (this.draggedType === 'column' && dropTarget.classList.contains('column')) {
                dropTarget.classList.add('drag-over');
            }
        }
    },

    handleDrop(e) {
        e.preventDefault();
        
        const dropTarget = e.target.closest('.card, .column-cards, .column');
        if (!dropTarget || !this.draggedElement) return;

        document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));

        if (this.draggedType === 'card' && (dropTarget.classList.contains('card') || dropTarget.classList.contains('column-cards'))) {
            this.handleCardDrop(dropTarget);
        } else if (this.draggedType === 'column' && dropTarget.classList.contains('column')) {
            this.handleColumnDrop(dropTarget);
        }
    },

    async handleCardDrop(target) {
        const cardId = this.draggedElement.dataset.cardId;
        let targetColumn, targetCard, newPosition;

        if (target.classList.contains('column-cards')) {
            targetColumn = target;
            targetCard = null;
            newPosition = target.children.length;
        } else if (target.classList.contains('card')) {
            targetColumn = target.closest('.column-cards');
            targetCard = target;
            const cards = Array.from(targetColumn.children);
            newPosition = cards.indexOf(targetCard);
        }

        if (!targetColumn) return;

        const newColumnId = targetColumn.dataset.columnId;
        
        // Calculate position considering the dragged card removal
        const sourceCards = this.sourceColumn.querySelector('.column-cards').children;
        if (newColumnId === this.sourceColumn.dataset.columnId) {
            // Same column - adjust for removed card
            const oldPosition = Array.from(sourceCards).indexOf(this.draggedElement);
            if (oldPosition < newPosition) {
                newPosition--;
            }
        }

        try {
            await Api.post('/api/card.php', {
                action: 'move',
                board_id: Board.currentBoard.id,
                card_id: cardId,
                column_id: newColumnId,
                position: newPosition
            });
            await Board.loadBoard(Board.currentBoard.id);
        } catch (error) {
            console.error('Move failed:', error);
            alert('Failed to move card: ' + (error.message || 'Unknown error'));
        }
    },

    async handleColumnDrop(target) {
        const draggedColumnId = this.draggedElement.dataset.columnId;
        const targetColumnId = target.dataset.columnId;

        if (draggedColumnId === targetColumnId) return;

        // Get all column IDs in new order
        const columnsContainer = document.getElementById('columns-container');
        const allColumns = Array.from(columnsContainer.querySelectorAll('.column'));
        const draggedIndex = allColumns.findIndex(c => c.dataset.columnId === draggedColumnId);
        const targetIndex = allColumns.findIndex(c => c.dataset.columnId === targetColumnId);

        // Reorder array
        const draggedCol = allColumns.splice(draggedIndex, 1)[0];
        allColumns.splice(targetIndex, 0, draggedCol);

        const newOrder = allColumns.map(c => c.dataset.columnId);

        try {
            await Api.post('/api/column.php', {
                action: 'move',
                board_id: Board.currentBoard.id,
                column_ids: newOrder
            });
            await Board.loadBoard(Board.currentBoard.id);
        } catch (error) {
            console.error('Column move failed:', error);
            alert('Failed to move column: ' + (error.message || 'Unknown error'));
        }
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => DragDrop.init());
