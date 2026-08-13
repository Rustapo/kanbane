<?php

namespace App\Services;

use App\Storage\JsonStorage;
use App\Helpers\Functions;

class ImportService
{
    private JsonStorage $storage;

    public function __construct(JsonStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Import board JSON with merge strategy
     */
    public function importBoardMerge(array $importData, string $userId): array
    {
        // Validate structure
        if (!isset($importData['id']) || !isset($importData['name'])) {
            throw new \Exception('Invalid board data', 422);
        }

        $boardId = $importData['id'];
        
        // Check if board exists
        $existingBoard = null;
        try {
            $existingBoard = $this->storage->readBoardRaw($boardId);
        } catch (\Exception $e) {
            // Board doesn't exist, will create new
        }

        if ($existingBoard) {
            // Merge: update existing fields, preserve local data
            return $this->storage->updateBoard($boardId, function ($board) use ($importData, $userId) {
                // Update basic fields
                if (isset($importData['name'])) {
                    $board['name'] = $importData['name'];
                }
                if (isset($importData['description'])) {
                    $board['description'] = $importData['description'];
                }

                // Merge columns (by ID)
                if (isset($importData['columns'])) {
                    foreach ($importData['columns'] as $importCol) {
                        $existingIndex = $this->findColumnById($board, $importCol['id']);
                        if ($existingIndex !== -1) {
                            // Update existing
                            $board['columns'][$existingIndex] = $this->mergeCardContainer(
                                $board['columns'][$existingIndex],
                                $importCol
                            );
                        } else {
                            // Add new
                            $board['columns'][] = $importCol;
                        }
                    }
                }

                // Merge tags
                if (isset($importData['tags'])) {
                    foreach ($importData['tags'] as $importTag) {
                        if (!$this->tagExists($board, $importTag['id'])) {
                            $board['tags'][] = $importTag;
                        }
                    }
                }

                $board['revision'] = ($board['revision'] ?? 0) + 1;
                $board['updated_at'] = date('c');

                return [
                    'data' => $board,
                    'message' => 'Board imported (merged)'
                ];
            });
        } else {
            // Create new board
            $importData['revision'] = 1;
            $importData['created_at'] = date('c');
            $importData['updated_at'] = date('c');
            
            // Ensure required fields
            if (!isset($importData['columns'])) {
                $importData['columns'] = [];
            }
            if (!isset($importData['tags'])) {
                $importData['tags'] = [];
            }
            if (!isset($importData['members'])) {
                $importData['members'] = [];
            }

            $this->storage->createBoard($boardId, $importData);

            return [
                'data' => ['board_id' => $boardId],
                'message' => 'Board imported (new)'
            ];
        }
    }

    /**
     * Import board JSON with replace strategy (creates backup first)
     */
    public function importBoardReplace(array $importData, string $userId): array
    {
        // Validate structure
        if (!isset($importData['id']) || !isset($importData['name'])) {
            throw new \Exception('Invalid board data', 422);
        }

        $boardId = $importData['id'];
        
        // Create backup if board exists
        try {
            $existingBoard = $this->storage->readBoardRaw($boardId);
            if ($existingBoard) {
                $backupService = new BackupService($this->storage);
                $backupService->createBoardBackup($boardId);
            }
        } catch (\Exception $e) {
            // No existing board, skip backup
        }

        // Prepare board data
        $importData['revision'] = 1;
        $importData['updated_at'] = date('c');
        
        if (!isset($importData['columns'])) {
            $importData['columns'] = [];
        }
        if (!isset($importData['tags'])) {
            $importData['tags'] = [];
        }
        if (!isset($importData['members'])) {
            $importData['members'] = [];
        }
        if (!isset($importData['tokens'])) {
            $importData['tokens'] = [];
        }

        // Store the board
        $this->storage->createBoard($boardId, $importData);

        return [
            'data' => ['board_id' => $boardId],
            'message' => 'Board imported (replaced)'
        ];
    }

    /**
     * Import system data (users, global tags) with merge
     */
    public function importSystemMerge(array $importData, string $adminId): array
    {
        return $this->storage->updateSystem(function ($system) use ($importData, $adminId) {
            // Import users
            if (isset($importData['users'])) {
                foreach ($importData['users'] as $importUser) {
                    if (isset($importUser['id']) && !$this->userExistsInSystem($importUser['id'])) {
                        // User file would need to be created separately
                        // This is a simplified merge
                    }
                }
            }

            // Import global tags
            if (isset($importData['global_tags'])) {
                foreach ($importData['global_tags'] as $importTag) {
                    if (!$this->globalTagExists($system, $importTag['id'])) {
                        $system['global_tags'][] = $importTag;
                    }
                }
            }

            $system['updated_at'] = date('c');

            return [
                'data' => $system,
                'message' => 'System data imported (merged)'
            ];
        });
    }

    /**
     * Validate import JSON structure
     */
    public function validateImport(string $jsonString): array
    {
        $data = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'valid' => false,
                'error' => 'Invalid JSON: ' . json_last_error_msg()
            ];
        }

        // Check for board structure
        if (isset($data['id']) && isset($data['name']) && isset($data['columns'])) {
            return [
                'valid' => true,
                'type' => 'board',
                'board_id' => $data['id'],
                'name' => $data['name']
            ];
        }

        // Check for system structure
        if (isset($data['schema_version']) || isset($data['admin'])) {
            return [
                'valid' => true,
                'type' => 'system'
            ];
        }

        return [
            'valid' => false,
            'error' => 'Unknown import format'
        ];
    }

    private function findColumnById(array $board, string $columnId): int
    {
        foreach ($board['columns'] as $index => $col) {
            if ($col['id'] === $columnId) {
                return $index;
            }
        }
        return -1;
    }

    private function mergeCardContainer(array $existing, array $imported): array
    {
        // Merge cards within column
        if (isset($imported['cards'])) {
            if (!isset($existing['cards'])) {
                $existing['cards'] = [];
            }

            foreach ($imported['cards'] as $importCard) {
                $existingIndex = $this->findCardById($existing, $importCard['id']);
                if ($existingIndex !== -1) {
                    // Update existing card
                    $existing['cards'][$existingIndex] = array_merge(
                        $existing['cards'][$existingIndex],
                        $importCard
                    );
                } else {
                    // Add new card
                    $existing['cards'][] = $importCard;
                }
            }
        }

        // Update column metadata
        if (isset($imported['title'])) {
            $existing['title'] = $imported['title'];
        }
        if (isset($imported['position'])) {
            $existing['position'] = $imported['position'];
        }

        return $existing;
    }

    private function findCardById(array $column, string $cardId): int
    {
        if (!isset($column['cards'])) {
            return -1;
        }
        
        foreach ($column['cards'] as $index => $card) {
            if ($card['id'] === $cardId) {
                return $index;
            }
        }
        return -1;
    }

    private function tagExists(array $board, string $tagId): bool
    {
        if (!isset($board['tags'])) {
            return false;
        }
        
        foreach ($board['tags'] as $tag) {
            if ($tag['id'] === $tagId) {
                return true;
            }
        }
        return false;
    }

    private function globalTagExists(array $system, string $tagId): bool
    {
        if (!isset($system['global_tags'])) {
            return false;
        }
        
        foreach ($system['global_tags'] as $tag) {
            if ($tag['id'] === $tagId) {
                return true;
            }
        }
        return false;
    }

    private function userExistsInSystem(string $userId): bool
    {
        // Simplified check - in reality would need to check user files
        return false;
    }
}
