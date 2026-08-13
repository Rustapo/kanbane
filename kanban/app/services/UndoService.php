<?php

namespace App\Services;

use App\Storage\JsonStorage;

class UndoService
{
    private JsonStorage $storage;
    private HistoryService $historyService;

    public function __construct(JsonStorage $storage, HistoryService $historyService)
    {
        $this->storage = $storage;
        $this->historyService = $historyService;
    }

    /**
     * Record an undoable action
     * Stores the reverse operation needed to undo
     */
    public function recordAction(string $boardId, string $userId, string $actionType, array $reverseData): void
    {
        // Store in session-temporary storage (last undoable action per user per board)
        // For simplicity, we'll store the reverse data in a way that can be retrieved
        
        // The actual undo state is stored implicitly via history
        // Each history event contains enough data to reverse it
        
        $this->historyService->record($boardId, 'UNDO_RECORD', [
            'action_type' => $actionType,
            'reverse_data' => $reverseData,
            'user_id' => $userId
        ], $userId);
    }

    /**
     * Perform undo for the last action by this user on this board
     */
    public function undoLastAction(string $boardId, string $userId): array
    {
        // Get recent history for this user
        $history = $this->historyService->getRecent($boardId, 100);
        
        if (empty($history['data'])) {
            throw new \Exception('No actions to undo', 400);
        }

        // Find the last actionable event by this user
        $undoableEvents = ['CREATE_CARD', 'UPDATE_CARD', 'DELETE_CARD', 
                          'CREATE_COLUMN', 'UPDATE_COLUMN', 'DELETE_COLUMN',
                          'ARCHIVE_CARD', 'RESTORE_CARD',
                          'ADD_COMMENT', 'UPDATE_COMMENT', 'DELETE_COMMENT',
                          'CREATE_CHECKLIST', 'DELETE_CHECKLIST',
                          'ASSIGN_USER', 'UNASSIGN_USER'];

        $lastEvent = null;
        foreach ($history['data'] as $event) {
            if ($event['user_id'] === $userId && in_array($event['event_type'], $undoableEvents)) {
                $lastEvent = $event;
                break;
            }
        }

        if (!$lastEvent) {
            throw new \Exception('No undoable actions found', 400);
        }

        // Execute reverse operation based on event type
        return $this->executeReverse($boardId, $lastEvent, $userId);
    }

    /**
     * Execute reverse operation for a given event
     */
    private function executeReverse(string $boardId, array $event, string $userId): array
    {
        $eventType = $event['event_type'];
        $data = $event['data'];

        switch ($eventType) {
            case 'CREATE_CARD':
                return $this->reverseCreateCard($boardId, $data, $userId);
            
            case 'UPDATE_CARD':
                return $this->reverseUpdateCard($boardId, $data, $userId);
            
            case 'DELETE_CARD':
            case 'ARCHIVE_CARD':
                return $this->reverseDeleteCard($boardId, $data, $userId);
            
            case 'CREATE_COLUMN':
                return $this->reverseCreateColumn($boardId, $data, $userId);
            
            case 'ADD_COMMENT':
                return $this->reverseAddComment($boardId, $data, $userId);
            
            case 'DELETE_COMMENT':
                return $this->reverseDeleteComment($boardId, $data, $userId);

            default:
                throw new \Exception('Cannot undo this action type', 400);
        }
    }

    private function reverseCreateCard(string $boardId, array $data, string $userId): array
    {
        $cardId = $data['card_id'] ?? null;
        if (!$cardId) {
            throw new \Exception('Cannot undo: missing card ID', 400);
        }

        return $this->storage->updateBoard($boardId, function ($board) use ($cardId, $userId) {
            foreach ($board['columns'] as &$column) {
                foreach ($column['cards'] as $index => $card) {
                    if ($card['id'] === $cardId) {
                        // Archive instead of hard delete
                        $card['status'] = 'archived';
                        $card['updated_at'] = date('c');
                        
                        $this->historyService->record($boardId, 'UNDO', [
                            'original_event' => 'CREATE_CARD',
                            'card_id' => $cardId,
                            'action' => 'archived_created_card'
                        ], $userId);

                        return [
                            'data' => ['undone' => 'CREATE_CARD', 'card_id' => $cardId],
                            'message' => 'Card creation undone (card archived)'
                        ];
                    }
                }
            }

            throw new \Exception('Card not found', 404);
        });
    }

    private function reverseUpdateCard(string $boardId, array $data, string $userId): array
    {
        $cardId = $data['card_id'] ?? null;
        $previousState = $data['previous_state'] ?? null;

        if (!$cardId || !$previousState) {
            throw new \Exception('Cannot undo: missing data', 400);
        }

        return $this->storage->updateBoard($boardId, function ($board) use ($cardId, $previousState, $userId) {
            foreach ($board['columns'] as &$column) {
                foreach ($column['cards'] as &$card) {
                    if ($card['id'] === $cardId) {
                        // Restore previous state for specific fields
                        $restoreFields = ['title', 'description', 'priority', 'due_date', 'tags', 'assignees'];
                        
                        foreach ($restoreFields as $field) {
                            if (isset($previousState[$field])) {
                                $card[$field] = $previousState[$field];
                            }
                        }
                        
                        $card['updated_at'] = date('c');

                        $this->historyService->record($boardId, 'UNDO', [
                            'original_event' => 'UPDATE_CARD',
                            'card_id' => $cardId,
                            'action' => 'restored_previous_state'
                        ], $userId);

                        return [
                            'data' => ['undone' => 'UPDATE_CARD', 'card_id' => $cardId],
                            'message' => 'Card update undone'
                        ];
                    }
                }
            }

            throw new \Exception('Card not found', 404);
        });
    }

    private function reverseDeleteCard(string $boardId, array $data, string $userId): array
    {
        // For delete/archive undo, we need the full card state from history
        $previousState = $data['previous_state'] ?? null;
        
        if (!$previousState) {
            throw new \Exception('Cannot undo: no card state available', 400);
        }

        // This would require storing full card state in history
        // Simplified: just indicate that restore is possible via archive
        return [
            'data' => ['note' => 'Restore from archive instead'],
            'message' => 'Use archive restore for this action'
        ];
    }

    private function reverseCreateColumn(string $boardId, array $data, string $userId): array
    {
        $columnId = $data['column_id'] ?? null;
        if (!$columnId) {
            throw new \Exception('Cannot undo: missing column ID', 400);
        }

        return $this->storage->updateBoard($boardId, function ($board) use ($columnId, $userId) {
            foreach ($board['columns'] as $index => $column) {
                if ($column['id'] === $columnId) {
                    // Archive the column (move cards first if any)
                    $column['status'] = 'archived';
                    $column['updated_at'] = date('c');

                    $this->historyService->record($boardId, 'UNDO', [
                        'original_event' => 'CREATE_COLUMN',
                        'column_id' => $columnId,
                        'action' => 'archived_created_column'
                    ], $userId);

                    return [
                        'data' => ['undone' => 'CREATE_COLUMN', 'column_id' => $columnId],
                        'message' => 'Column creation undone (column archived)'
                    ];
                }
            }

            throw new \Exception('Column not found', 404);
        });
    }

    private function reverseAddComment(string $boardId, array $data, string $userId): array
    {
        $commentId = $data['comment_id'] ?? null;
        if (!$commentId) {
            throw new \Exception('Cannot undo: missing comment ID', 400);
        }

        return $this->storage->updateBoard($boardId, function ($board) use ($commentId, $userId) {
            foreach ($board['columns'] as &$column) {
                foreach ($column['cards'] as &$card) {
                    if (isset($card['comments'])) {
                        foreach ($card['comments'] as $index => $comment) {
                            if ($comment['id'] === $commentId) {
                                array_splice($card['comments'], $index, 1);
                                $card['updated_at'] = date('c');

                                $this->historyService->record($boardId, 'UNDO', [
                                    'original_event' => 'ADD_COMMENT',
                                    'comment_id' => $commentId,
                                    'action' => 'deleted_added_comment'
                                ], $userId);

                                return [
                                    'data' => ['undone' => 'ADD_COMMENT', 'comment_id' => $commentId],
                                    'message' => 'Comment addition undone'
                                ];
                            }
                        }
                    }
                }
            }

            throw new \Exception('Comment not found', 404);
        });
    }

    private function reverseDeleteComment(string $boardId, array $data, string $userId): array
    {
        // Would need full comment state from history
        return [
            'data' => ['note' => 'Comment restoration requires full state'],
            'message' => 'Cannot fully undo comment deletion without state'
        ];
    }
}
