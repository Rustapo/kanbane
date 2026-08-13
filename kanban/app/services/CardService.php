<?php
/**
 * CardService - Бизнес-логика работы с карточками
 */

class CardService
{
    private BoardStorage $boardStorage;
    private HistoryStorage $historyStorage;

    public function __construct()
    {
        $this->boardStorage = new BoardStorage();
        $this->historyStorage = new HistoryStorage();
    }

    /**
     * Создать карточку
     */
    public function createCard(string $boardId, string $columnId, string $title, string $userId): array
    {
        $board = $this->boardStorage->getBoard($boardId);
        if (!$board) {
            throw new NotFoundException('Board not found');
        }

        $columnIndex = $this->findColumnIndex($board, $columnId);
        if ($columnIndex === -1) {
            throw new NotFoundException('Column not found');
        }

        $cardId = $this->generateId('card_');
        $now = date('c');

        $card = [
            'id' => $cardId,
            'title' => $title,
            'description' => '',
            'status' => 'active',
            'priority' => 'Medium',
            'due_date' => null,
            'tags' => [],
            'assignees' => [],
            'checklist' => [],
            'position' => count($board['columns'][$columnIndex]['cards']),
            'created_at' => $now,
            'updated_at' => $now
        ];

        $board['columns'][$columnIndex]['cards'][] = $card;
        $board['revision']++;
        $board['updated_at'] = $now;

        $this->boardStorage->saveBoard($board);

        $this->historyStorage->addEvent(
            $boardId,
            'CREATE_CARD',
            ['card_id' => $cardId, 'column_id' => $columnId, 'title' => $title],
            $userId
        );

        return $card;
    }

    /**
     * Обновить карточку с проверкой revision
     */
    public function updateCard(string $boardId, string $cardId, array $updates, int $expectedRevision, string $userId): array
    {
        $board = $this->boardStorage->getBoard($boardId);
        if (!$board) {
            throw new NotFoundException('Board not found');
        }

        // Проверка revision
        if ($board['revision'] !== $expectedRevision) {
            throw new ConflictException(
                'The board has been modified by another user.',
                $board['revision']
            );
        }

        $location = $this->findCardLocation($board, $cardId);
        if ($location === null) {
            throw new NotFoundException('Card not found');
        }

        [$columnIndex, $cardIndex] = $location;
        $card = &$board['columns'][$columnIndex]['cards'][$cardIndex];

        $allowedFields = ['title', 'description', 'priority', 'due_date', 'tags', 'assignees'];
        $changedFields = [];

        foreach ($updates as $key => $value) {
            if (in_array($key, $allowedFields) && isset($card[$key])) {
                if ($card[$key] !== $value) {
                    $changedFields[] = $key;
                    $card[$key] = $value;
                }
            }
        }

        if (empty($changedFields)) {
            return $card;
        }

        $card['updated_at'] = date('c');
        $board['revision']++;
        $board['updated_at'] = date('c');

        $this->boardStorage->saveBoard($board);

        $this->historyStorage->addEvent(
            $boardId,
            'UPDATE_CARD',
            ['card_id' => $cardId, 'changes' => $changedFields],
            $userId
        );

        return $card;
    }

    /**
     * Переместить карточку
     */
    public function moveCard(string $boardId, string $cardId, string $targetColumnId, int $targetPosition, int $expectedRevision, string $userId): bool
    {
        $board = $this->boardStorage->getBoard($boardId);
        if (!$board) {
            throw new NotFoundException('Board not found');
        }

        // Проверка revision
        if ($board['revision'] !== $expectedRevision) {
            throw new ConflictException(
                'The board has been modified by another user.',
                $board['revision']
            );
        }

        $location = $this->findCardLocation($board, $cardId);
        if ($location === null) {
            throw new NotFoundException('Card not found');
        }

        [$sourceColumnIndex, $cardIndex] = $location;
        $targetColumnIndex = $this->findColumnIndex($board, $targetColumnId);
        
        if ($targetColumnIndex === -1) {
            throw new NotFoundException('Target column not found');
        }

        // Извлекаем карточку
        $card = $board['columns'][$sourceColumnIndex]['cards'][$cardIndex];
        array_splice($board['columns'][$sourceColumnIndex]['cards'], $cardIndex, 1);

        // Вставляем в новое место
        array_splice($board['columns'][$targetColumnIndex]['cards'], $targetPosition, 0, [$card]);

        // Обновляем позиции
        foreach ($board['columns'][$targetColumnIndex]['cards'] as $index => $c) {
            $board['columns'][$targetColumnIndex]['cards'][$index]['position'] = $index;
        }

        $board['revision']++;
        $board['updated_at'] = date('c');

        $this->boardStorage->saveBoard($board);

        $this->historyStorage->addEvent(
            $boardId,
            'MOVE_CARD',
            [
                'card_id' => $cardId,
                'from_column' => $board['columns'][$sourceColumnIndex]['id'],
                'to_column' => $targetColumnId,
                'position' => $targetPosition
            ],
            $userId
        );

        return true;
    }

    /**
     * Архивировать карточку
     */
    public function archiveCard(string $boardId, string $cardId, string $userId): bool
    {
        $board = $this->boardStorage->getBoard($boardId);
        if (!$board) {
            throw new NotFoundException('Board not found');
        }

        $location = $this->findCardLocation($board, $cardId);
        if ($location === null) {
            throw new NotFoundException('Card not found');
        }

        [$columnIndex, $cardIndex] = $location;
        $board['columns'][$columnIndex]['cards'][$cardIndex]['status'] = 'archived';
        $board['columns'][$columnIndex]['cards'][$cardIndex]['updated_at'] = date('c');
        $board['revision']++;
        $board['updated_at'] = date('c');

        $this->boardStorage->saveBoard($board);

        $this->historyStorage->addEvent(
            $boardId,
            'ARCHIVE_CARD',
            ['card_id' => $cardId],
            $userId
        );

        return true;
    }

    /**
     * Восстановить карточку
     */
    public function restoreCard(string $boardId, string $cardId, string $userId): bool
    {
        $board = $this->boardStorage->getBoard($boardId);
        if (!$board) {
            throw new NotFoundException('Board not found');
        }

        $location = $this->findCardLocation($board, $cardId);
        if ($location === null) {
            throw new NotFoundException('Card not found');
        }

        [$columnIndex, $cardIndex] = $location;
        $board['columns'][$columnIndex]['cards'][$cardIndex]['status'] = 'active';
        $board['columns'][$columnIndex]['cards'][$cardIndex]['updated_at'] = date('c');
        $board['revision']++;
        $board['updated_at'] = date('c');

        $this->boardStorage->saveBoard($board);

        $this->historyStorage->addEvent(
            $boardId,
            'RESTORE_CARD',
            ['card_id' => $cardId],
            $userId
        );

        return true;
    }

    /**
     * Найти расположение карточки
     * @return array|null [columnIndex, cardIndex] или null если не найдено
     */
    private function findCardLocation(array $board, string $cardId): ?array
    {
        foreach ($board['columns'] as $colIndex => $column) {
            foreach ($column['cards'] as $cardIndex => $card) {
                if ($card['id'] === $cardId) {
                    return [$colIndex, $cardIndex];
                }
            }
        }
        return null;
    }

    /**
     * Найти индекс колонки
     */
    private function findColumnIndex(array $board, string $columnId): int
    {
        foreach ($board['columns'] as $index => $column) {
            if ($column['id'] === $columnId) {
                return $index;
            }
        }
        return -1;
    }

    /**
     * Сгенерировать ID
     */
    private function generateId(string $prefix): string
    {
        return $prefix . bin2hex(random_bytes(8));
    }
}
