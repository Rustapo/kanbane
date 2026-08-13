<?php
/**
 * UserService - Бизнес-логика работы с пользователями
 */

class UserService
{
    private UserStorage $userStorage;
    private BoardStorage $boardStorage;
    private HistoryStorage $historyStorage;

    public function __construct()
    {
        $this->userStorage = new UserStorage();
        $this->boardStorage = new BoardStorage();
        $this->historyStorage = new HistoryStorage();
    }

    /**
     * Создать нового пользователя
     */
    public function createUser(string $name): array
    {
        $user = $this->userStorage->createUser(['name' => $name]);
        
        $this->historyStorage->addEvent(
            'system',
            'CREATE_USER',
            ['user_id' => $user['id'], 'name' => $name],
            'system'
        );

        return $user;
    }

    /**
     * Получить пользователя по ID
     */
    public function getUser(string $userId): ?array
    {
        return $this->userStorage->getUser($userId);
    }

    /**
     * Обновить пользователя
     */
    public function updateUser(string $userId, array $updates, string $actorId): array
    {
        $user = $this->userStorage->getUser($userId);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        $updated = $this->userStorage->updateUser($userId, $updates);

        $this->historyStorage->addEvent(
            'system',
            'UPDATE_USER',
            ['user_id' => $userId, 'updates' => array_keys($updates)],
            $actorId
        );

        return $updated;
    }

    /**
     * Архивировать пользователя
     */
    public function archiveUser(string $userId, string $actorId): bool
    {
        $result = $this->userStorage->archiveUser($userId);
        
        if ($result) {
            $this->historyStorage->addEvent(
                'system',
                'ARCHIVE_USER',
                ['user_id' => $userId],
                $actorId
            );
        }

        return $result;
    }

    /**
     * Восстановить пользователя
     */
    public function restoreUser(string $userId, string $actorId): bool
    {
        $result = $this->userStorage->restoreUser($userId);
        
        if ($result) {
            $this->historyStorage->addEvent(
                'system',
                'RESTORE_USER',
                ['user_id' => $userId],
                $actorId
            );
        }

        return $result;
    }

    /**
     * Получить всех активных пользователей
     */
    public function getAllActiveUsers(): array
    {
        return $this->userStorage->getAllActiveUsers();
    }

    /**
     * Добавить пользователя на доску
     */
    public function addUserToBoard(string $userId, string $boardId, string $permission, string $actorId): bool
    {
        $user = $this->userStorage->getUser($userId);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        $board = $this->boardStorage->getBoard($boardId);
        if (!$board) {
            throw new NotFoundException('Board not found');
        }

        // Проверяем, есть ли уже пользователь на доске
        $memberIndex = -1;
        foreach ($board['members'] as $index => $member) {
            if ($member['user_id'] === $userId) {
                $memberIndex = $index;
                break;
            }
        }

        $memberData = [
            'user_id' => $userId,
            'permission' => $permission,
            'added_at' => date('c')
        ];

        if ($memberIndex >= 0) {
            // Обновляем существующего участника
            $board['members'][$memberIndex] = $memberData;
        } else {
            // Добавляем нового участника
            $board['members'][] = $memberData;
        }

        $this->boardStorage->saveBoard($board);

        $this->historyStorage->addEvent(
            $boardId,
            'ADD_USER_TO_BOARD',
            ['user_id' => $userId, 'board_id' => $boardId, 'permission' => $permission],
            $actorId
        );

        return true;
    }

    /**
     * Удалить пользователя с доски
     */
    public function removeUserFromBoard(string $userId, string $boardId, string $actorId): bool
    {
        $board = $this->boardStorage->getBoard($boardId);
        if (!$board) {
            throw new NotFoundException('Board not found');
        }

        $newMembers = [];
        foreach ($board['members'] as $member) {
            if ($member['user_id'] !== $userId) {
                $newMembers[] = $member;
            }
        }

        $board['members'] = $newMembers;
        $this->boardStorage->saveBoard($board);

        $this->historyStorage->addEvent(
            $boardId,
            'REMOVE_USER_FROM_BOARD',
            ['user_id' => $userId, 'board_id' => $boardId],
            $actorId
        );

        return true;
    }

    /**
     * Получить доски пользователя
     */
    public function getUserBoards(string $userId): array
    {
        $allBoards = $this->boardStorage->getAllActiveBoards();
        $userBoards = [];

        foreach ($allBoards as $board) {
            foreach ($board['members'] as $member) {
                if ($member['user_id'] === $userId) {
                    $board['my_permission'] = $member['permission'];
                    $userBoards[] = $board;
                    break;
                }
            }
        }

        return $userBoards;
    }

    /**
     * Проверить доступ пользователя к доске
     */
    public function checkBoardAccess(string $userId, string $boardId): ?string
    {
        $board = $this->boardStorage->getBoard($boardId);
        if (!$board) {
            return null;
        }

        foreach ($board['members'] as $member) {
            if ($member['user_id'] === $userId) {
                return $member['permission'];
            }
        }

        return null;
    }
}
