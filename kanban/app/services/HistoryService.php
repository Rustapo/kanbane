<?php

namespace App\Services;

use App\Storage\JsonStorage;
use App\Helpers\Functions;

class HistoryService
{
    private JsonStorage $storage;

    public function __construct(JsonStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Record an event to board history (JSONL format)
     */
    public function record(string $boardId, string $eventType, array $data, string $userId): void
    {
        $historyFile = $this->storage->getHistoryFilePath($boardId);
        
        $event = [
            'timestamp' => date('c'),
            'event_type' => $eventType,
            'user_id' => $userId,
            'data' => $data
        ];

        $line = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Append to JSONL file with lock
        $handle = fopen($historyFile, 'a');
        if ($handle === false) {
            throw new \Exception('Cannot open history file', 500);
        }

        try {
            flock($handle, LOCK_EX);
            fwrite($handle, $line . PHP_EOL);
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Get recent history events for a board
     */
    public function getRecent(string $boardId, int $limit = 50): array
    {
        $historyFile = $this->storage->getHistoryFilePath($boardId);
        
        if (!file_exists($historyFile)) {
            return ['data' => [], 'message' => 'No history'];
        }

        $lines = file($historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return ['data' => [], 'message' => 'Cannot read history'];
        }

        // Get last N lines
        $recentLines = array_slice($lines, -$limit);
        $events = [];

        foreach ($recentLines as $line) {
            $event = json_decode($line, true);
            if ($event !== null) {
                $events[] = $event;
            }
        }

        // Reverse to show newest first
        $events = array_reverse($events);

        return [
            'data' => $events,
            'message' => 'History retrieved'
        ];
    }

    /**
     * Get history events filtered by type
     */
    public function getByType(string $boardId, string $eventType, int $limit = 50): array
    {
        $historyFile = $this->storage->getHistoryFilePath($boardId);
        
        if (!file_exists($historyFile)) {
            return ['data' => [], 'message' => 'No history'];
        }

        $lines = file($historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return ['data' => [], 'message' => 'Cannot read history'];
        }

        $events = [];
        foreach ($lines as $line) {
            $event = json_decode($line, true);
            if ($event !== null && $event['event_type'] === $eventType) {
                $events[] = $event;
                if (count($events) >= $limit) {
                    break;
                }
            }
        }

        $events = array_reverse($events);

        return [
            'data' => $events,
            'message' => 'Filtered history retrieved'
        ];
    }

    /**
     * Get history events for a specific card
     */
    public function getByCard(string $boardId, string $cardId, int $limit = 50): array
    {
        $allHistory = $this->getRecent($boardId, 500); // Get more to filter
        $filtered = [];

        foreach ($allHistory['data'] as $event) {
            if (isset($event['data']['card_id']) && $event['data']['card_id'] === $cardId) {
                $filtered[] = $event;
                if (count($filtered) >= $limit) {
                    break;
                }
            }
        }

        return [
            'data' => $filtered,
            'message' => 'Card history retrieved'
        ];
    }

    /**
     * Clear old history (keep last N events)
     */
    public function prune(string $boardId, int $keepLast = 1000): array
    {
        $historyFile = $this->storage->getHistoryFilePath($boardId);
        
        if (!file_exists($historyFile)) {
            return ['data' => ['pruned' => 0], 'message' => 'No history to prune'];
        }

        $lines = file($historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || count($lines) <= $keepLast) {
            return ['data' => ['pruned' => 0], 'message' => 'Nothing to prune'];
        }

        $prunedCount = count($lines) - $keepLast;
        $keptLines = array_slice($lines, -$keepLast);

        // Write back atomically
        $tempFile = $historyFile . '.tmp';
        $handle = fopen($tempFile, 'w');
        if ($handle === false) {
            throw new \Exception('Cannot create temp history file', 500);
        }

        try {
            flock($handle, LOCK_EX);
            foreach ($keptLines as $line) {
                fwrite($handle, $line . PHP_EOL);
            }
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        rename($tempFile, $historyFile);

        return [
            'data' => ['pruned' => $prunedCount],
            'message' => 'History pruned'
        ];
    }

    /**
     * Export history to array (for backup)
     */
    public function export(string $boardId): array
    {
        return $this->getRecent($boardId, 10000); // Large limit for export
    }
}
