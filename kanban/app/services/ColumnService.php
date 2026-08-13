<?php
/**
 * ColumnService - Бизнес-логика работы с колонками
 */

class ColumnService
{
    private BoardStorage $boardStorage;
    private HistoryStorage $historyStorage;

    public function __construct()
    {
        $this->boardStorage = new BoardStorage();
        $this->historyStorage = new HistoryStorage();
    }

    /**
     * Создать колонку
     */
    public function createColumn(string $boardId, string $name, string $userId): array
    {
        $board = $this->boardStorage->getBoard($boardId);
        if (!$board) {
            throw new NotFoundException('Board not found');
        }

        $columnId = $this->generateId('col_');
        $now = date('c');

        $column = [
            'id' => $columnId,
            'name' => $name,
            'status' => 'active',
            'position' => count($board['columns']),
            'created_at' => $now,
            'updated_at' => $now,
            'cards' => []
        ];

        $board['columns'][] = $column;
        $board['revision']++;
        $board['updated_at'] = $now;

        $this->boardStorage->saveBoard($board);

        $this->historyStorage->addEvent(
            $boardId,
            'CREATE_COLUMN',
            ['column_id' => $columnId, 'name' => $name],
            $userId
        );

        return $column;
    }

    /**
     * Переименовать колонку
     */
    public function renameColumn(string $boardId, string $columnId, string $newName, string $userId): bool
    {
        $board = $this->boardStorage->getBoard($boardId);
        if (!$board) {
            throw new NotFoundException('Board not found');
        }

        $columnIndex = $this->findColumnIndex($board, $columnId);
        if ($columnIndex === -1) {
            throw new NotFoundException('Column not found');
        }

        $now = date('c');
        $oldName = $board['columns'][$columnIndex]['name'];
        $board['columns'][$columnIndex]['name'] = $newName;
        $board['columns'][$columnIndex]['updated_at'] = $now;
        $board['revision']++;
        $board['updated_at'] = $now;

        $this->boardStorage->saveBoard($board);

        $this->historyStorage->addEvent(
            $boardId,
            'UPDATE_COLUMN',
            ['column_id' => $columnId, 'old_name' => $oldName, 'new_name' => $newName],
            $userId
        );

        return true;
    }

    /**
     * Архивировать колонку (с переносом карт)
     */
    public function archiveColumn(string $boardId, string $columnId, ?string $destinationColumnId, string $userId): bool
    {
        $board = $this->boardStorage->getBoard($boardId);
        if (!$board) {
            throw new NotFoundException('Board not found');
        }

        $columnIndex = $this->findColumnIndex($board, $columnId);
        if ($columnIndex === -1) {
            throw new NotFoundException('Column not found');
        }

        $column = $board['columns'][$columnIndex];
        
        // Если есть активные карты и указана колонка назначения
        if (!empty($column['cards']) && $destinationColumnId) {
            $destIndex = $this->findColumnIndex($board, $destinationColumnId);
            if ($destIndex === -1) {
                throw new NotFoundException('Destination column not found');
            }

            // Переносим активные карты
            foreach ($column['cards'] as $card) {
                if (($card['status'] ?? 'active') === 'active') {
                    $board['columns'][$destIndex]['cards'][] = $card;
                }
            }
        }

        $now = date('c');
        $board['columns'][$columnIndex]['status'] = 'archived';
        $board['columns'][$columnIndex]['updated_at'] = $now;
        $board['revision']++;
        $board['updated_at'] = $now;

        $this->boardStorage->saveBoard($board);

        $this->historyStorage->addEvent(
            $boardId,
            'ARCHIVE_COLUMN',
            ['column_id' => $columnId, 'destination_column_id' => $destinationColumnId],
            $userId
        );

        return true;
    }

    /**
     * Восстановить колонку
     */
    public function restoreColumn(string $boardId, string $columnId, string $userId): bool
    {
        $board = $this->boardStorage->getBoard($boardId);
        if (!$board) {
            throw new NotFoundException('Board not found');
        }

        $columnIndex = $this->findColumnIndex($board, $columnId);
        if ($columnIndex === -1) {
            throw new NotFoundException('Column not found');
        }

        $now = date('c');
        $board['columns'][$columnIndex]['status'] = 'active';
        $board['columns'][$columnIndex]['updated_at'] = $now;
        $board['revision']++;
        $board['updated_at'] = $now;

        $this->boardStorage->saveBoard($board);

        $this->historyStorage->addEvent(
            $boardId,
            'RESTORE_COLUMN',
            ['column_id' => $columnId],
            $userId
        );

        return true;
    }

    /**
     * Изменить порядок колонок
     */
    public function reorderColumns(string $boardId, array $columnOrder, string $userId): bool
    {
        $board = $this->boardStorage->getBoard($boardId);
        if (!$board) {
            throw new NotFoundException('Board not found');
        }

        $newColumns = [];
        foreach ($columnOrder as $index => $columnId) {
            $colIndex = $this->findColumnIndex($board, $columnId);
            if ($colIndex !== -1) {
                $board['columns'][$colIndex]['position'] = $index;
                $newColumns[] = $board['columns'][$colIndex];
            }
        }

        $board['columns'] = $newColumns;
        $board['revision']++;
        $board['updated_at'] = date('c');

        $this->boardStorage->saveBoard($board);

        $this->historyStorage->addEvent(
            $boardId,
            'MOVE_COLUMN',
            ['column_order' => $columnOrder],
            $userId
        );

        return true;
    }

    /**
     * Найти индекс колонки в массиве
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
