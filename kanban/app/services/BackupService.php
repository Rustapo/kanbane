<?php

namespace App\Services;

use App\Storage\JsonStorage;
use App\Helpers\Functions;

class BackupService
{
    private JsonStorage $storage;

    public function __construct(JsonStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Create full system backup as ZIP
     */
    public function createSystemBackup(): array
    {
        $backupDir = $this->storage->getBackupsDir();
        $timestamp = date('Y-m-d_H-i-s');
        $backupFilename = "backup_system_{$timestamp}.zip";
        $backupPath = $backupDir . '/' . $backupFilename;

        $zip = new \ZipArchive();
        if ($zip->open($backupPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Cannot create backup archive', 500);
        }

        $dataDir = dirname($this->storage->getSystemFilePath());
        
        // Add system.json
        $systemFile = $this->storage->getSystemFilePath();
        if (file_exists($systemFile)) {
            $zip->addFile($systemFile, 'data/system.json');
        }

        // Add users
        $usersDir = $dataDir . '/users';
        if (is_dir($usersDir)) {
            $this->addDirectoryToZip($zip, $usersDir, 'data/users');
        }

        // Add boards
        $boardsDir = $dataDir . '/boards';
        if (is_dir($boardsDir)) {
            $this->addDirectoryToZip($zip, $boardsDir, 'data/boards');
        }

        // Add history
        $historyDir = $dataDir . '/history';
        if (is_dir($historyDir)) {
            $this->addDirectoryToZip($zip, $historyDir, 'data/history');
        }

        $zip->close();

        return [
            'data' => [
                'filename' => $backupFilename,
                'path' => $backupPath,
                'size' => filesize($backupPath)
            ],
            'message' => 'System backup created'
        ];
    }

    /**
     * Create board-specific backup
     */
    public function createBoardBackup(string $boardId): array
    {
        $backupDir = $this->storage->getBackupsDir();
        $timestamp = date('Y-m-d_H-i-s');
        $backupFilename = "backup_board_{$boardId}_{$timestamp}.zip";
        $backupPath = $backupDir . '/' . $backupFilename;

        $zip = new \ZipArchive();
        if ($zip->open($backupPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Cannot create backup archive', 500);
        }

        // Add board file
        $boardFile = $this->storage->getBoardFilePath($boardId);
        if (file_exists($boardFile)) {
            $zip->addFile($boardFile, "data/boards/{$boardId}.json");
        }

        // Add board history
        $historyFile = $this->storage->getHistoryFilePath($boardId);
        if (file_exists($historyFile)) {
            $zip->addFile($historyFile, "data/history/{$boardId}.jsonl");
        }

        $zip->close();

        return [
            'data' => [
                'filename' => $backupFilename,
                'board_id' => $boardId,
                'size' => filesize($backupPath)
            ],
            'message' => 'Board backup created'
        ];
    }

    /**
     * List available backups
     */
    public function listBackups(): array
    {
        $backupDir = $this->storage->getBackupsDir();
        
        if (!is_dir($backupDir)) {
            return ['data' => [], 'message' => 'No backups'];
        }

        $files = scandir($backupDir);
        $backups = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filePath = $backupDir . '/' . $file;
            if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'zip') {
                $backups[] = [
                    'filename' => $file,
                    'size' => filesize($filePath),
                    'created_at' => date('c', filemtime($filePath)),
                    'type' => strpos($file, '_board_') !== false ? 'board' : 'system'
                ];
            }
        }

        // Sort by date descending
        usort($backups, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return [
            'data' => $backups,
            'message' => 'Backups listed'
        ];
    }

    /**
     * Delete a backup file
     */
    public function deleteBackup(string $filename): array
    {
        // Prevent path traversal
        $basename = basename($filename);
        if ($basename !== $filename) {
            throw new \Exception('Invalid filename', 400);
        }

        $backupDir = $this->storage->getBackupsDir();
        $backupPath = $backupDir . '/' . $basename;

        if (!file_exists($backupPath)) {
            throw new \Exception('Backup not found', 404);
        }

        if (!unlink($backupPath)) {
            throw new \Exception('Cannot delete backup', 500);
        }

        return [
            'data' => ['deleted' => $basename],
            'message' => 'Backup deleted'
        ];
    }

    /**
     * Export board as JSON (single file)
     */
    public function exportBoardJson(string $boardId): array
    {
        return $this->storage->readBoard($boardId, function ($board) {
            return [
                'data' => $board,
                'message' => 'Board exported'
            ];
        });
    }

    /**
     * Helper: Add directory contents to ZIP recursively
     */
    private function addDirectoryToZip(\ZipArchive $zip, string $sourceDir, string $destDir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = $destDir . '/' . str_replace($sourceDir . '/', '', $file->getPathname());
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }
    }
}
