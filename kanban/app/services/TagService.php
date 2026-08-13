<?php

namespace App\Services;

use App\Storage\JsonStorage;
use App\Helpers\Functions;

class TagService
{
    private JsonStorage $storage;

    public function __construct(JsonStorage $storage)
    {
        $this->storage = $storage;
    }

    public function createGlobal(string $name, string $color): array
    {
        return $this->storage->updateSystem(function ($system) use ($name, $color) {
            // Check if tag already exists
            foreach ($system['global_tags'] as $tag) {
                if (strtolower($tag['name']) === strtolower($name)) {
                    throw new \Exception('Tag already exists', 409);
                }
            }

            $tagId = Functions::generateId('tag_');
            $newTag = [
                'id' => $tagId,
                'name' => $name,
                'color' => $color,
                'type' => 'global',
                'status' => 'active',
                'created_at' => date('c')
            ];

            $system['global_tags'][] = $newTag;

            return [
                'data' => $newTag,
                'message' => 'Global tag created'
            ];
        });
    }

    public function createBoard(string $boardId, string $name, string $color): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($name, $color) {
            // Check if tag already exists in board
            foreach ($board['tags'] as $tag) {
                if (strtolower($tag['name']) === strtolower($name)) {
                    throw new \Exception('Tag already exists', 409);
                }
            }

            $tagId = Functions::generateId('tag_');
            $newTag = [
                'id' => $tagId,
                'name' => $name,
                'color' => $color,
                'type' => 'board',
                'status' => 'active',
                'created_at' => date('c')
            ];

            $board['tags'][] = $newTag;

            return [
                'data' => $newTag,
                'message' => 'Board tag created'
            ];
        });
    }

    public function archiveGlobal(string $tagId): array
    {
        return $this->storage->updateSystem(function ($system) use ($tagId) {
            foreach ($system['global_tags'] as &$tag) {
                if ($tag['id'] === $tagId) {
                    if ($tag['status'] === 'archived') {
                        throw new \Exception('Tag already archived', 400);
                    }
                    $tag['status'] = 'archived';
                    $tag['updated_at'] = date('c');
                    
                    return [
                        'data' => ['archived' => $tagId],
                        'message' => 'Global tag archived'
                    ];
                }
            }

            throw new \Exception('Tag not found', 404);
        });
    }

    public function archiveBoard(string $boardId, string $tagId): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($tagId) {
            foreach ($board['tags'] as &$tag) {
                if ($tag['id'] === $tagId) {
                    if ($tag['status'] === 'archived') {
                        throw new \Exception('Tag already archived', 400);
                    }
                    $tag['status'] = 'archived';
                    $tag['updated_at'] = date('c');
                    
                    return [
                        'data' => ['archived' => $tagId],
                        'message' => 'Board tag archived'
                    ];
                }
            }

            throw new \Exception('Tag not found', 404);
        });
    }

    public function restoreGlobal(string $tagId): array
    {
        return $this->storage->updateSystem(function ($system) use ($tagId) {
            foreach ($system['global_tags'] as &$tag) {
                if ($tag['id'] === $tagId) {
                    if ($tag['status'] !== 'archived') {
                        throw new \Exception('Tag is not archived', 400);
                    }
                    $tag['status'] = 'active';
                    $tag['updated_at'] = date('c');
                    
                    return [
                        'data' => ['restored' => $tagId],
                        'message' => 'Global tag restored'
                    ];
                }
            }

            throw new \Exception('Tag not found', 404);
        });
    }

    public function restoreBoard(string $boardId, string $tagId): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($tagId) {
            foreach ($board['tags'] as &$tag) {
                if ($tag['id'] === $tagId) {
                    if ($tag['status'] !== 'archived') {
                        throw new \Exception('Tag is not archived', 400);
                    }
                    $tag['status'] = 'active';
                    $tag['updated_at'] = date('c');
                    
                    return [
                        'data' => ['restored' => $tagId],
                        'message' => 'Board tag restored'
                    ];
                }
            }

            throw new \Exception('Tag not found', 404);
        });
    }

    public function assignToCard(string $boardId, string $cardId, string $tagId): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($cardId, $tagId) {
            // Find card
            foreach ($board['columns'] as &$column) {
                foreach ($column['cards'] as &$card) {
                    if ($card['id'] === $cardId) {
                        // Check if tag already assigned
                        if (isset($card['tags']) && in_array($tagId, $card['tags'])) {
                            throw new \Exception('Tag already assigned', 400);
                        }
                        
                        if (!isset($card['tags'])) {
                            $card['tags'] = [];
                        }
                        $card['tags'][] = $tagId;
                        $card['updated_at'] = date('c');
                        
                        return [
                            'data' => ['assigned' => $tagId],
                            'message' => 'Tag assigned to card'
                        ];
                    }
                }
            }

            throw new \Exception('Card not found', 404);
        });
    }

    public function unassignFromCard(string $boardId, string $cardId, string $tagId): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($cardId, $tagId) {
            foreach ($board['columns'] as &$column) {
                foreach ($column['cards'] as &$card) {
                    if ($card['id'] === $cardId && isset($card['tags'])) {
                        $index = array_search($tagId, $card['tags']);
                        if ($index !== false) {
                            array_splice($card['tags'], $index, 1);
                            $card['updated_at'] = date('c');
                            
                            return [
                                'data' => ['unassigned' => $tagId],
                                'message' => 'Tag removed from card'
                            ];
                        }
                    }
                }
            }

            throw new \Exception('Tag not assigned to card', 404);
        });
    }

    public function getAllTags(): array
    {
        return $this->storage->readSystem(function ($system) {
            return [
                'data' => [
                    'global' => $system['global_tags'] ?? [],
                ],
                'message' => 'Tags retrieved'
            ];
        });
    }

    public function getBoardTags(string $boardId): array
    {
        return $this->storage->readBoard($boardId, function ($board) {
            return [
                'data' => $board['tags'] ?? [],
                'message' => 'Board tags retrieved'
            ];
        });
    }
}
