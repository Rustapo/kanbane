<?php

namespace App\Services;

use App\Storage\JsonStorage;
use App\Helpers\Functions;

class CommentService
{
    private JsonStorage $storage;

    public function __construct(JsonStorage $storage)
    {
        $this->storage = $storage;
    }

    public function create(string $boardId, string $cardId, string $userId, string $text): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($cardId, $userId, $text) {
            $cardIndex = $this->findCardIndex($board, $cardId);
            if ($cardIndex === -1) {
                throw new \Exception('Card not found', 404);
            }

            $commentId = Functions::generateId('cmt_');
            $newComment = [
                'id' => $commentId,
                'author_id' => $userId,
                'text' => $text,
                'created_at' => date('c'),
                'updated_at' => date('c')
            ];

            if (!isset($board['columns'][$cardIndex]['comments'])) {
                $board['columns'][$cardIndex]['comments'] = [];
            }
            
            // Note: cards are inside columns, need to find card properly
            foreach ($board['columns'] as &$column) {
                foreach ($column['cards'] as &$card) {
                    if ($card['id'] === $cardId) {
                        if (!isset($card['comments'])) {
                            $card['comments'] = [];
                        }
                        $card['comments'][] = $newComment;
                        $card['updated_at'] = date('c');
                        
                        return [
                            'data' => $newComment,
                            'message' => 'Comment added'
                        ];
                    }
                }
            }

            throw new \Exception('Card not found', 404);
        });
    }

    public function update(string $boardId, string $cardId, string $commentId, string $userId, string $text, bool $isAdmin): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($cardId, $commentId, $userId, $text, $isAdmin) {
            foreach ($board['columns'] as &$column) {
                foreach ($column['cards'] as &$card) {
                    if ($card['id'] === $cardId && isset($card['comments'])) {
                        foreach ($card['comments'] as &$comment) {
                            if ($comment['id'] === $commentId) {
                                // Check ownership or admin
                                if ($comment['author_id'] !== $userId && !$isAdmin) {
                                    throw new \Exception('Not authorized', 403);
                                }
                                
                                $comment['text'] = $text;
                                $comment['updated_at'] = date('c');
                                $card['updated_at'] = date('c');
                                
                                return [
                                    'data' => $comment,
                                    'message' => 'Comment updated'
                                ];
                            }
                        }
                    }
                }
            }

            throw new \Exception('Comment not found', 404);
        });
    }

    public function delete(string $boardId, string $cardId, string $commentId, string $userId, bool $isAdmin): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($cardId, $commentId, $userId, $isAdmin) {
            foreach ($board['columns'] as &$column) {
                foreach ($column['cards'] as &$card) {
                    if ($card['id'] === $cardId && isset($card['comments'])) {
                        foreach ($card['comments'] as $index => $comment) {
                            if ($comment['id'] === $commentId) {
                                // Check ownership or admin
                                if ($comment['author_id'] !== $userId && !$isAdmin) {
                                    throw new \Exception('Not authorized', 403);
                                }
                                
                                array_splice($card['comments'], $index, 1);
                                $card['updated_at'] = date('c');
                                
                                return [
                                    'data' => ['deleted' => $commentId],
                                    'message' => 'Comment deleted'
                                ];
                            }
                        }
                    }
                }
            }

            throw new \Exception('Comment not found', 404);
        });
    }

    private function findCardInBoard(array $board, string $cardId): ?array
    {
        foreach ($board['columns'] as $column) {
            foreach ($column['cards'] as $card) {
                if ($card['id'] === $cardId) {
                    return $card;
                }
            }
        }
        return null;
    }
}
