<?php

namespace App\Services;

use App\Storage\JsonStorage;
use App\Helpers\Functions;

class ChecklistService
{
    private JsonStorage $storage;

    public function __construct(JsonStorage $storage)
    {
        $this->storage = $storage;
    }

    public function create(string $boardId, string $cardId, string $title): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($cardId, $title) {
            $checklistId = Functions::generateId('chk_');
            $newChecklist = [
                'id' => $checklistId,
                'title' => $title,
                'items' => [],
                'created_at' => date('c')
            ];

            foreach ($board['columns'] as &$column) {
                foreach ($column['cards'] as &$card) {
                    if ($card['id'] === $cardId) {
                        if (!isset($card['checklists'])) {
                            $card['checklists'] = [];
                        }
                        $card['checklists'][] = $newChecklist;
                        $card['updated_at'] = date('c');
                        
                        return [
                            'data' => $newChecklist,
                            'message' => 'Checklist created'
                        ];
                    }
                }
            }

            throw new \Exception('Card not found', 404);
        });
    }

    public function addItem(string $boardId, string $cardId, string $checklistId, string $text): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($cardId, $checklistId, $text) {
            foreach ($board['columns'] as &$column) {
                foreach ($column['cards'] as &$card) {
                    if ($card['id'] === $cardId && isset($card['checklists'])) {
                        foreach ($card['checklists'] as &$checklist) {
                            if ($checklist['id'] === $checklistId) {
                                $itemId = Functions::generateId('chki_');
                                $newItem = [
                                    'id' => $itemId,
                                    'text' => $text,
                                    'completed' => false,
                                    'created_at' => date('c')
                                ];
                                
                                $checklist['items'][] = $newItem;
                                $card['updated_at'] = date('c');
                                
                                return [
                                    'data' => $newItem,
                                    'message' => 'Checklist item added'
                                ];
                            }
                        }
                    }
                }
            }

            throw new \Exception('Checklist not found', 404);
        });
    }

    public function toggleItem(string $boardId, string $cardId, string $checklistId, string $itemId): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($cardId, $checklistId, $itemId) {
            foreach ($board['columns'] as &$column) {
                foreach ($column['cards'] as &$card) {
                    if ($card['id'] === $cardId && isset($card['checklists'])) {
                        foreach ($card['checklists'] as &$checklist) {
                            if ($checklist['id'] === $checklistId) {
                                foreach ($checklist['items'] as &$item) {
                                    if ($item['id'] === $itemId) {
                                        $item['completed'] = !$item['completed'];
                                        $item['updated_at'] = date('c');
                                        $card['updated_at'] = date('c');
                                        
                                        return [
                                            'data' => $item,
                                            'message' => 'Checklist item toggled'
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            throw new \Exception('Checklist item not found', 404);
        });
    }

    public function updateItem(string $boardId, string $cardId, string $checklistId, string $itemId, string $text): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($cardId, $checklistId, $itemId, $text) {
            foreach ($board['columns'] as &$column) {
                foreach ($column['cards'] as &$card) {
                    if ($card['id'] === $cardId && isset($card['checklists'])) {
                        foreach ($card['checklists'] as &$checklist) {
                            if ($checklist['id'] === $checklistId) {
                                foreach ($checklist['items'] as &$item) {
                                    if ($item['id'] === $itemId) {
                                        $item['text'] = $text;
                                        $item['updated_at'] = date('c');
                                        $card['updated_at'] = date('c');
                                        
                                        return [
                                            'data' => $item,
                                            'message' => 'Checklist item updated'
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            throw new \Exception('Checklist item not found', 404);
        });
    }

    public function deleteItem(string $boardId, string $cardId, string $checklistId, string $itemId): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($cardId, $checklistId, $itemId) {
            foreach ($board['columns'] as &$column) {
                foreach ($column['cards'] as &$card) {
                    if ($card['id'] === $cardId && isset($card['checklists'])) {
                        foreach ($card['checklists'] as &$checklist) {
                            if ($checklist['id'] === $checklistId) {
                                foreach ($checklist['items'] as $index => $item) {
                                    if ($item['id'] === $itemId) {
                                        array_splice($checklist['items'], $index, 1);
                                        $card['updated_at'] = date('c');
                                        
                                        return [
                                            'data' => ['deleted' => $itemId],
                                            'message' => 'Checklist item deleted'
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            throw new \Exception('Checklist item not found', 404);
        });
    }

    public function delete(string $boardId, string $cardId, string $checklistId): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($cardId, $checklistId) {
            foreach ($board['columns'] as &$column) {
                foreach ($column['cards'] as &$card) {
                    if ($card['id'] === $cardId && isset($card['checklists'])) {
                        foreach ($card['checklists'] as $index => $checklist) {
                            if ($checklist['id'] === $checklistId) {
                                array_splice($card['checklists'], $index, 1);
                                $card['updated_at'] = date('c');
                                
                                return [
                                    'data' => ['deleted' => $checklistId],
                                    'message' => 'Checklist deleted'
                                ];
                            }
                        }
                    }
                }
            }

            throw new \Exception('Checklist not found', 404);
        });
    }
}
