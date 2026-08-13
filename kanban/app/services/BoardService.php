<?php
/**
 * BoardService - бизнес-логика работы с досками
 */

declare(strict_types=1);

namespace App\Services;

use App\Storage\JsonStorage;

class BoardService
{
    private JsonStorage $storage;

    public function __construct(JsonStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Создание новой доски
     */
    public function createBoard(string $name, string $description = ''): ?array
    {
        $boardId = 'brd_' . generateId(BOARD_ID_LENGTH - 4);
        $boardFile = BOARDS_DIR . "/{$boardId}.json";

        $board = [
            'id' => $boardId,
            'name' => sanitizeString($name),
            'description' => sanitizeString($description),
            'revision' => 0,
            'status' => 'active',
            'created_at' => currentTimestamp(),
            'updated_at' => currentTimestamp(),
            'members' => [],
            'columns' => [],
            'tags' => [],
        ];

        if (!$this->storage->write($boardFile, $board)) {
            return null;
        }

        // Запись в историю
        appendHistory($boardId, [
            'event' => 'CREATE_BOARD',
            'board_id' => $boardId,
            'data' => ['name' => $board['name']],
        ]);

        return $board;
    }

    /**
     * Получение доски по ID
     */
    public function getBoard(string $boardId): ?array
    {
        $boardFile = BOARDS_DIR . "/{$boardId}.json";
        return $this->storage->read($boardFile);
    }

    /**
     * Получение всех досок
     */
    public function getAllBoards(): array
    {
        $boards = [];
        $files = glob(BOARDS_DIR . '/*.json');
        
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $board = $this->storage->read($file);
            if ($board !== null && ($board['status'] ?? '') === 'active') {
                $boards[] = [
                    'id' => $board['id'],
                    'name' => $board['name'],
                    'description' => $board['description'] ?? '',
                    'status' => $board['status'],
                    'updated_at' => $board['updated_at'],
                ];
            }
        }

        return $boards;
    }

    /**
     * Обновление доски
     */
    public function updateBoard(string $boardId, array $data): array|false
    {
        $boardFile = BOARDS_DIR . "/{$boardId}.json";
        $board = $this->storage->read($boardFile);
        
        if ($board === null) {
            return false;
        }

        $result = $this->storage->updateWithRevisionCheck(
            $boardFile,
            function (array &$board) use ($data) {
                if (isset($data['name'])) {
                    $board['name'] = sanitizeString($data['name']);
                }
                if (isset($data['description'])) {
                    $board['description'] = sanitizeString($data['description']);
                }
                return true;
            },
            $board['revision'] ?? 0
        );

        if ($result['success']) {
            appendHistory($boardId, [
                'event' => 'UPDATE_BOARD',
                'board_id' => $boardId,
                'data' => $data,
            ]);
            return $result['data'];
        }

        return false;
    }

    /**
     * Архивирование доски
     */
    public function archiveBoard(string $boardId): bool
    {
        $boardFile = BOARDS_DIR . "/{$boardId}.json";
        $board = $this->storage->read($boardFile);
        
        if ($board === null) {
            return false;
        }

        $result = $this->storage->updateWithRevisionCheck(
            $boardFile,
            function (array &$board) {
                $board['status'] = 'archived';
                return true;
            },
            $board['revision'] ?? 0
        );

        if ($result['success']) {
            appendHistory($boardId, [
                'event' => 'ARCHIVE_BOARD',
                'board_id' => $boardId,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Восстановление доски из архива
     */
    public function restoreBoard(string $boardId): bool
    {
        $boardFile = BOARDS_DIR . "/{$boardId}.json";
        $board = $this->storage->read($boardFile);
        
        if ($board === null) {
            return false;
        }

        $result = $this->storage->updateWithRevisionCheck(
            $boardFile,
            function (array &$board) {
                $board['status'] = 'active';
                return true;
            },
            $board['revision'] ?? 0
        );

        if ($result['success']) {
            appendHistory($boardId, [
                'event' => 'RESTORE_BOARD',
                'board_id' => $boardId,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Получение текущей revision доски
     */
    public function getRevision(string $boardId): ?int
    {
        return $this->storage->getRevision(BOARDS_DIR . "/{$boardId}.json");
    }

    /**
     * Добавление пользователя на доску
     */
    public function addUserToBoard(string $boardId, string $userId, string $role): bool
    {
        $boardFile = BOARDS_DIR . "/{$boardId}.json";
        $board = $this->storage->read($boardFile);
        
        if ($board === null) {
            return false;
        }

        // Проверка что пользователь уже не добавлен
        foreach ($board['members'] as $member) {
            if ($member['user_id'] === $userId) {
                return false;
            }
        }

        $result = $this->storage->updateWithRevisionCheck(
            $boardFile,
            function (array &$board) use ($userId, $role) {
                $board['members'][] = [
                    'user_id' => $userId,
                    'added_at' => currentTimestamp(),
                    'tokens' => [],
                ];
                return true;
            },
            $board['revision'] ?? 0
        );

        if ($result['success']) {
            appendHistory($boardId, [
                'event' => 'ADD_USER_TO_BOARD',
                'board_id' => $boardId,
                'data' => ['user_id' => $userId, 'role' => $role],
            ]);
            return true;
        }

        return false;
    }

    /**
     * Удаление пользователя с доски
     */
    public function removeUserFromBoard(string $boardId, string $userId): bool
    {
        $boardFile = BOARDS_DIR . "/{$boardId}.json";
        $board = $this->storage->read($boardFile);
        
        if ($board === null) {
            return false;
        }

        $result = $this->storage->updateWithRevisionCheck(
            $boardFile,
            function (array &$board) use ($userId) {
                $board['members'] = array_filter(
                    $board['members'],
                    fn($m) => $m['user_id'] !== $userId
                );
                return true;
            },
            $board['revision'] ?? 0
        );

        if ($result['success']) {
            appendHistory($boardId, [
                'event' => 'REMOVE_USER_FROM_BOARD',
                'board_id' => $boardId,
                'data' => ['user_id' => $userId],
            ]);
            return true;
        }

        return false;
    }
}
