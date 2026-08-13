<?php
/**
 * TokenAuth - аутентификация по токенам для обычных пользователей
 */

declare(strict_types=1);

namespace App\Auth;

use App\Storage\JsonStorage;

class TokenAuth
{
    private JsonStorage $storage;

    public function __construct(JsonStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Аутентификация пользователя по токену
     * Возвращает user_id и права доступа к доске или null
     */
    public function authenticate(string $token, string $boardId): ?array
    {
        if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
            return null;
        }

        $tokenHash = hashToken($token);
        
        // Загружаем данные доски
        $boardFile = BOARDS_DIR . "/{$boardId}.json";
        $board = $this->storage->read($boardFile);
        
        if ($board === null || !isset($board['members'])) {
            return null;
        }

        // Ищем пользователя с matching token hash
        foreach ($board['members'] as $member) {
            if (!isset($member['user_id'], $member['tokens'])) {
                continue;
            }

            foreach ($member['tokens'] as $tokenData) {
                if (!isset($tokenData['hash'], $tokenData['status'], $tokenData['role'])) {
                    continue;
                }

                if ($tokenData['status'] !== 'active') {
                    continue;
                }

                if (hash_equals($tokenData['hash'], $tokenHash)) {
                    return [
                        'user_id' => $member['user_id'],
                        'role' => $tokenData['role'], // view или edit
                        'board_id' => $boardId,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Проверка прав доступа
     */
    public function hasPermission(?array $auth, string $requiredRole): bool
    {
        if ($auth === null) {
            return false;
        }

        $roleHierarchy = [
            'view' => 1,
            'edit' => 2,
            'admin' => 3,
        ];

        $userRole = $auth['role'] ?? 'view';
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
        $userLevel = $roleHierarchy[$userRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Генерация нового токена для пользователя на доске
     */
    public function generateToken(string $boardId, string $userId, string $role): ?string
    {
        if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
            return null;
        }
        if (!Security::isValidId($userId, USER_ID_LENGTH)) {
            return null;
        }
        if (!in_array($role, ['view', 'edit'], true)) {
            return null;
        }

        $boardFile = BOARDS_DIR . "/{$boardId}.json";
        
        $result = $this->storage->updateWithRevisionCheck(
            $boardFile,
            function (array &$board) use ($userId, $role) {
                // Генерируем новый токен
                $rawToken = generateToken(32);
                $tokenHash = hashToken($rawToken);

                // Находим или создаём запись пользователя
                $memberIndex = null;
                foreach ($board['members'] as $index => $member) {
                    if ($member['user_id'] === $userId) {
                        $memberIndex = $index;
                        break;
                    }
                }

                if ($memberIndex === null) {
                    // Добавляем нового пользователя
                    $board['members'][] = [
                        'user_id' => $userId,
                        'added_at' => currentTimestamp(),
                        'tokens' => [],
                    ];
                    $memberIndex = count($board['members']) - 1;
                }

                // Добавляем новый токен
                $board['members'][$memberIndex]['tokens'][] = [
                    'id' => generateId(8),
                    'hash' => $tokenHash,
                    'role' => $role,
                    'status' => 'active',
                    'created_at' => currentTimestamp(),
                    'last_used_at' => null,
                ];

                return true;
            },
            $board['revision'] ?? 0
        );

        if ($result['success']) {
            return $rawToken;
        }

        return null;
    }

    /**
     * Отзыв токена
     */
    public function revokeToken(string $boardId, string $userId, string $tokenId): bool
    {
        if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
            return false;
        }
        if (!Security::isValidId($userId, USER_ID_LENGTH)) {
            return false;
        }

        $boardFile = BOARDS_DIR . "/{$boardId}.json";
        $board = $this->storage->read($boardFile);
        
        if ($board === null) {
            return false;
        }

        $result = $this->storage->updateWithRevisionCheck(
            $boardFile,
            function (array &$board) use ($userId, $tokenId) {
                foreach ($board['members'] as &$member) {
                    if ($member['user_id'] !== $userId) {
                        continue;
                    }

                    foreach ($member['tokens'] as &$tokenData) {
                        if ($tokenData['id'] === $tokenId) {
                            $tokenData['status'] = 'revoked';
                            $tokenData['revoked_at'] = currentTimestamp();
                            return true;
                        }
                    }
                }
                return false;
            },
            $board['revision'] ?? 0
        );

        return $result['success'] ?? false;
    }
}
