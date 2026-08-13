<?php
/**
 * BoardStorage - Специализированное хранилище для досок
 */

class BoardStorage extends JsonStorage
{
    private const DIR = 'boards';

    /**
     * Получить путь к файлу доски
     */
    public function getFilePath(string $boardId): string
    {
        return $this->getBasePath() . '/' . self::DIR . '/' . $boardId . '.json';
    }

    /**
     * Получить данные доски
     */
    public function getBoard(string $boardId): ?array
    {
        $data = $this->read(self::DIR . '/' . $boardId . '.json');
        return $data ?: null;
    }

    /**
     * Сохранить данные доски (с блокировкой)
     */
    public function saveBoard(array $boardData): bool
    {
        if (empty($boardData['id'])) {
            throw new InvalidArgumentException('Board ID is required');
        }
        return $this->write(self::DIR . '/' . $boardData['id'] . '.json', $boardData);
    }

    /**
     * Создать новую доску
     */
    public function createBoard(array $data): array
    {
        $boardId = $this->generateId('brd_');
        
        $now = date('c');
        $board = [
            'id' => $boardId,
            'name' => $data['name'] ?? 'New Board',
            'description' => $data['description'] ?? '',
            'revision' => 1,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
            'members' => $data['members'] ?? [],
            'columns' => $data['columns'] ?? [],
            'tags' => $data['tags'] ?? []
        ];

        $this->saveBoard($board);
        return $board;
    }

    /**
     * Обновить доску с проверкой revision
     */
    public function updateBoardWithRevision(string $boardId, array $updates, int $expectedRevision): array
    {
        $filePath = self::DIR . '/' . $boardId . '.json';
        
        return $this->updateWithRevisionCheck($filePath, $updates, $expectedRevision, function(&$current) use ($updates) {
            foreach ($updates as $key => $value) {
                if ($key !== 'revision' && $key !== 'id' && $key !== 'created_at') {
                    $current[$key] = $value;
                }
            }
            $current['updated_at'] = date('c');
            $current['revision']++;
            return $current;
        });
    }

    /**
     * Получить список всех активных досок
     */
    public function getAllActiveBoards(): array
    {
        $dirPath = $this->getBasePath() . '/' . self::DIR;
        if (!is_dir($dirPath)) {
            return [];
        }

        $boards = [];
        $files = glob($dirPath . '/*.json');
        
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) continue;
            
            $data = json_decode($content, true);
            if ($data && ($data['status'] ?? '') === 'active') {
                $boards[] = $data;
            }
        }

        return $boards;
    }
}
