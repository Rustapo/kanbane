/**
 * api.js - API клиент для взаимодействия с backend
 */

const API = {
    baseURL: '',

    /**
     * Выполнение GET запроса
     */
    async get(endpoint, params = {}) {
        const url = new URL(`${this.baseURL}${endpoint}`, window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            url.searchParams.append(key, value);
        });

        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
        });

        return this.handleResponse(response);
    },

    /**
     * Выполнение POST запроса
     */
    async post(endpoint, data = {}) {
        const response = await fetch(`${this.baseURL}${endpoint}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });

        return this.handleResponse(response);
    },

    /**
     * Обработка ответа
     */
    async handleResponse(response) {
        // 204 No Content
        if (response.status === 204) {
            return { success: true, data: null };
        }

        const contentType = response.headers.get('content-type');
        
        // Parse JSON если возможно
        let data;
        if (contentType && contentType.includes('application/json')) {
            data = await response.json();
        } else {
            data = { message: await response.text() };
        }

        // Успешный ответ
        if (response.ok) {
            return data;
        }

        // Ошибки
        throw new APIError(
            data.error || 'UNKNOWN_ERROR',
            data.message || 'An error occurred',
            response.status,
            data.revision
        );
    },
};

/**
 * Ошибка API
 */
class APIError extends Error {
    constructor(code, message, status, revision = null) {
        super(message);
        this.name = 'APIError';
        this.code = code;
        this.status = status;
        this.revision = revision;
    }

    get isConflict() {
        return this.status === 409;
    }

    get isNotFound() {
        return this.status === 404;
    }

    get isUnauthorized() {
        return this.status === 401;
    }

    get isForbidden() {
        return this.status === 403;
    }
}

/**
 * Board API
 */
const BoardAPI = {
    getList() {
        return API.get('/api/board.php', { action: 'list' });
    },

    get(boardId) {
        return API.get('/api/board.php', { action: 'get', board_id: boardId });
    },

    getRevision(boardId) {
        return API.get('/api/board.php', { action: 'revision', board_id: boardId });
    },

    create(name, description = '') {
        return API.post('/api/board.php', {
            action: 'create',
            name,
            description,
        });
    },

    update(boardId, updates) {
        return API.post('/api/board.php', {
            action: 'update',
            board_id: boardId,
            ...updates,
        });
    },

    archive(boardId) {
        return API.post('/api/board.php', {
            action: 'archive',
            board_id: boardId,
        });
    },
};

/**
 * Card API
 */
const CardAPI = {
    create(boardId, columnId, title) {
        return API.post('/api/card.php', {
            action: 'create',
            board_id: boardId,
            column_id: columnId,
            title,
        });
    },

    update(boardId, cardId, updates, expectedRevision) {
        return API.post('/api/card.php', {
            action: 'update',
            board_id: boardId,
            card_id: cardId,
            expected_revision: expectedRevision,
            ...updates,
        });
    },

    move(boardId, cardId, targetColumnId, targetPosition, expectedRevision) {
        return API.post('/api/card.php', {
            action: 'move',
            board_id: boardId,
            card_id: cardId,
            target_column_id: targetColumnId,
            target_position: targetPosition,
            expected_revision: expectedRevision,
        });
    },

    archive(boardId, cardId) {
        return API.post('/api/card.php', {
            action: 'archive',
            board_id: boardId,
            card_id: cardId,
        });
    },

    restore(boardId, cardId) {
        return API.post('/api/card.php', {
            action: 'restore',
            board_id: boardId,
            card_id: cardId,
        });
    },
};

/**
 * User API
 */
const UserAPI = {
    getList() {
        return API.get('/api/user.php', { action: 'list' });
    },

    get(userId) {
        return API.get('/api/user.php', { action: 'get', user_id: userId });
    },

    getBoards(userId) {
        return API.get('/api/user.php', { action: 'boards', user_id: userId });
    },

    create(name) {
        return API.post('/api/user.php', {
            action: 'create',
            name,
        });
    },

    update(userId, updates) {
        return API.post('/api/user.php', {
            action: 'update',
            user_id: userId,
            ...updates,
        });
    },

    archive(userId) {
        return API.post('/api/user.php', {
            action: 'archive',
            user_id: userId,
        });
    },

    restore(userId) {
        return API.post('/api/user.php', {
            action: 'restore',
            user_id: userId,
        });
    },

    addToBoard(userId, boardId, permission) {
        return API.post('/api/user.php', {
            action: 'add_to_board',
            user_id: userId,
            board_id: boardId,
            permission,
        });
    },

    removeFromBoard(userId, boardId) {
        return API.post('/api/user.php', {
            action: 'remove_from_board',
            user_id: userId,
            board_id: boardId,
        });
    },
};

// Export для использования в других модулях
window.API = API;
window.APIError = APIError;
window.BoardAPI = BoardAPI;
window.CardAPI = CardAPI;
window.UserAPI = UserAPI;
