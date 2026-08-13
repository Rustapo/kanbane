<?php

namespace App\Services;

use App\Storage\JsonStorage;
use App\Helpers\Functions;

class TokenService
{
    private JsonStorage $storage;

    public function __construct(JsonStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Create a user token for board access
     */
    public function createBoardToken(string $boardId, string $level, string $description = ''): array
    {
        if (!in_array($level, ['view', 'edit'])) {
            throw new \Exception('Invalid token level', 400);
        }

        return $this->storage->updateBoard($boardId, function ($board) use ($level, $description) {
            $tokenId = Functions::generateId('tok_');
            $secretToken = bin2hex(random_bytes(32)); // 64 char hex token
            
            $tokenData = [
                'id' => $tokenId,
                'level' => $level,
                'description' => $description,
                'status' => 'active',
                'created_at' => date('c'),
                'last_used_at' => null
            ];

            if (!isset($board['tokens'])) {
                $board['tokens'] = [];
            }
            
            // Store hash, not the raw token
            $tokenData['hash'] = hash('sha256', $secretToken);
            $board['tokens'][] = $tokenData;

            return [
                'data' => [
                    'token' => $secretToken, // Return only once!
                    'id' => $tokenId,
                    'level' => $level,
                    'description' => $description
                ],
                'message' => 'Token created. Save it now - it won\'t be shown again.'
            ];
        });
    }

    /**
     * Create admin access token
     */
    public function createAdminToken(string $description = ''): array
    {
        return $this->storage->updateSystem(function ($system) use ($description) {
            $tokenId = Functions::generateId('tok_');
            $secretToken = bin2hex(random_bytes(32));
            
            $tokenData = [
                'id' => $tokenId,
                'type' => 'admin',
                'description' => $description,
                'status' => 'active',
                'created_at' => date('c'),
                'last_used_at' => null,
                'hash' => hash('sha256', $secretToken)
            ];

            if (!isset($system['admin_tokens'])) {
                $system['admin_tokens'] = [];
            }
            
            $system['admin_tokens'][] = $tokenData;

            return [
                'data' => [
                    'token' => $secretToken,
                    'id' => $tokenId,
                    'type' => 'admin'
                ],
                'message' => 'Admin token created. Save it now.'
            ];
        });
    }

    /**
     * Revoke a token
     */
    public function revokeBoardToken(string $boardId, string $tokenId): array
    {
        return $this->storage->updateBoard($boardId, function ($board) use ($tokenId) {
            if (!isset($board['tokens'])) {
                throw new \Exception('Token not found', 404);
            }

            foreach ($board['tokens'] as &$token) {
                if ($token['id'] === $tokenId) {
                    if ($token['status'] === 'revoked') {
                        throw new \Exception('Token already revoked', 400);
                    }
                    $token['status'] = 'revoked';
                    $token['revoked_at'] = date('c');
                    
                    return [
                        'data' => ['revoked' => $tokenId],
                        'message' => 'Token revoked'
                    ];
                }
            }

            throw new \Exception('Token not found', 404);
        });
    }

    public function revokeAdminToken(string $tokenId): array
    {
        return $this->storage->updateSystem(function ($system) use ($tokenId) {
            if (!isset($system['admin_tokens'])) {
                throw new \Exception('Token not found', 404);
            }

            foreach ($system['admin_tokens'] as &$token) {
                if ($token['id'] === $tokenId) {
                    if ($token['status'] === 'revoked') {
                        throw new \Exception('Token already revoked', 400);
                    }
                    $token['status'] = 'revoked';
                    $token['revoked_at'] = date('c');
                    
                    return [
                        'data' => ['revoked' => $tokenId],
                        'message' => 'Admin token revoked'
                    ];
                }
            }

            throw new \Exception('Token not found', 404);
        });
    }

    /**
     * Regenerate token (revoke old, create new)
     */
    public function regenerateBoardToken(string $boardId, string $tokenId, string $description = ''): array
    {
        // First revoke old
        $this->revokeBoardToken($boardId, $tokenId);
        
        // Then create new with same ID pattern but new secret
        return $this->createBoardToken($boardId, 'edit', $description . ' (regenerated)');
    }

    /**
     * Validate token and return board access info
     */
    public function validateBoardToken(string $boardId, string $token): ?array
    {
        return $this->storage->readBoard($boardId, function ($board) use ($token) {
            $tokenHash = hash('sha256', $token);
            
            if (!isset($board['tokens'])) {
                return null;
            }

            foreach ($board['tokens'] as &$tokenData) {
                if (hash_equals($tokenData['hash'], $tokenHash)) {
                    if ($tokenData['status'] !== 'active') {
                        return null; // Revoked or inactive
                    }
                    
                    // Update last used
                    $tokenData['last_used_at'] = date('c');
                    
                    return [
                        'level' => $tokenData['level'],
                        'board_id' => $boardId
                    ];
                }
            }

            return null;
        });
    }

    public function validateAdminToken(string $token): ?bool
    {
        return $this->storage->readSystem(function ($system) use ($token) {
            $tokenHash = hash('sha256', $token);
            
            if (!isset($system['admin_tokens'])) {
                return false;
            }

            foreach ($system['admin_tokens'] as $tokenData) {
                if (hash_equals($tokenData['hash'], $tokenHash)) {
                    return $tokenData['status'] === 'active';
                }
            }

            return false;
        });
    }

    /**
     * List all tokens for a board (without hashes)
     */
    public function listBoardTokens(string $boardId): array
    {
        return $this->storage->readBoard($boardId, function ($board) {
            $tokens = $board['tokens'] ?? [];
            
            // Remove sensitive data
            $safeTokens = array_map(function ($t) {
                return [
                    'id' => $t['id'],
                    'level' => $t['level'],
                    'description' => $t['description'] ?? '',
                    'status' => $t['status'],
                    'created_at' => $t['created_at'],
                    'last_used_at' => $t['last_used_at'] ?? null,
                    'revoked_at' => $t['revoked_at'] ?? null
                ];
            }, $tokens);

            return [
                'data' => $safeTokens,
                'message' => 'Tokens listed'
            ];
        });
    }

    public function listAdminTokens(): array
    {
        return $this->storage->readSystem(function ($system) {
            $tokens = $system['admin_tokens'] ?? [];
            
            $safeTokens = array_map(function ($t) {
                return [
                    'id' => $t['id'],
                    'type' => $t['type'],
                    'description' => $t['description'] ?? '',
                    'status' => $t['status'],
                    'created_at' => $t['created_at'],
                    'last_used_at' => $t['last_used_at'] ?? null,
                    'revoked_at' => $t['revoked_at'] ?? null
                ];
            }, $tokens);

            return [
                'data' => $safeTokens,
                'message' => 'Admin tokens listed'
            ];
        });
    }
}
