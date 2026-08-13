/**
 * Polling Module
 * Handles revision polling for real-time updates
 */

const Polling = {
    interval: 3000, // 3 seconds as per spec
    timer: null,
    isPolling: false,
    boardId: null,

    start(boardId) {
        if (this.isPolling && this.boardId === boardId) {
            return;
        }
        
        this.stop();
        this.boardId = boardId;
        this.isPolling = true;
        
        // Initial poll after short delay
        setTimeout(() => this.poll(), this.interval);
        this.timer = setInterval(() => this.poll(), this.interval);
        
        console.log('Polling started for board:', boardId);
    },

    stop() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        this.isPolling = false;
        this.boardId = null;
        console.log('Polling stopped');
    },

    async poll() {
        if (!this.boardId || !Board.currentRevision) {
            return;
        }

        try {
            const response = await Api.get(`/api/board.php?board_id=${this.boardId}&action=revision`);
            
            if (response.changed && response.revision !== Board.currentRevision) {
                console.log('Board changed, refreshing...');
                await Board.loadBoard(this.boardId);
                
                // Visual indicator of refresh
                document.body.classList.add('refreshed');
                setTimeout(() => document.body.classList.remove('refreshed'), 500);
            }
        } catch (error) {
            if (error.message.includes('401') || error.message.includes('403')) {
                // Auth error - stop polling
                this.stop();
            } else {
                console.warn('Polling error:', error.message);
            }
        }
    },

    setInterval(ms) {
        this.interval = ms;
        if (this.isPolling) {
            this.start(this.boardId);
        }
    }
};
