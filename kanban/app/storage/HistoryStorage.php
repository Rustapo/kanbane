<?php
/**
 * HistoryStorage - Хранилище истории операций в формате JSONL
 */

class HistoryStorage extends JsonStorage
{
    private const DIR = 'history';

    /**
     * Получить путь к файлу истории доски
     */
    public function getFilePath(string $boardId): string
    {
        return $this->getBasePath() . '/' . self::DIR . '/' . $boardId . '.jsonl';
    }

    /**
     * Добавить запись в историю
     */
    public function addEvent(string $boardId, string $eventType, array $data, string $userId): bool
    {
        $filePath = $this->getFilePath($boardId);
        
        $event = [
            'timestamp' => date('c'),
            'event_type' => $eventType,
            'user_id' => $userId,
            'data' => $data
        ];

        $line = json_encode($event, JSON_UNESCAPED_UNICODE) . PHP_EOL;

        // Открываем файл для добавления с блокировкой
        $handle = fopen($filePath, 'a');
        if ($handle === false) {
            throw new RuntimeException('Cannot open history file for writing');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Cannot acquire lock on history file');
            }

            if (fwrite($handle, $line) === false) {
                throw new RuntimeException('Cannot write to history file');
            }

            if (!fflush($handle)) {
                throw new RuntimeException('Cannot flush history file');
            }

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Получить последние N записей истории
     */
    public function getRecentEvents(string $boardId, int $limit = 50): array
    {
        $filePath = $this->getFilePath($boardId);
        
        if (!file_exists($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        // Берем последние N записей
        $recentLines = array_slice($lines, -$limit);
        $events = [];

        foreach ($recentLines as $line) {
            $event = json_decode($line, true);
            if ($event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Очистить историю доски
     */
    public function clearHistory(string $boardId): bool
    {
        $filePath = $this->getFilePath($boardId);
        
        if (!file_exists($filePath)) {
            return true;
        }

        $handle = fopen($filePath, 'w');
        if ($handle === false) {
            throw new RuntimeException('Cannot open history file for clearing');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Cannot acquire lock on history file');
            }

            ftruncate($handle, 0);
            fflush($handle);
            
            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
