<?php

/**
 * Comment API Endpoint
 * 
 * Handles comment operations on cards
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Auth\TokenAuth;
use App\Auth\AdminAuth;
use App\Auth\Permissions;
use App\Security\Security;
use App\Security\Input;
use App\Services\CommentService;
use App\Storage\JsonStorage;
use App\Helpers\Response;
use App\Helpers\Exceptions\ApiException;

header('Content-Type: application/json');
Security::setApiHeaders();

try {
    $storage = new JsonStorage();
    $auth = new TokenAuth($storage);
    $adminAuth = new AdminAuth($storage);
    
    $authResult = $auth->authenticateFromRequest();
    $isAdmin = false;
    $userId = null;
    
    if ($authResult) {
        $userId = $authResult['user_id'];
    } else {
        if ($adminAuth->isAuthenticated()) {
            $isAdmin = true;
            $userId = 'admin';
        } else {
            throw new ApiException('Unauthorized', 401);
        }
    }

    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        handlePost($storage, $authResult, $isAdmin, $userId);
    } elseif ($method === 'DELETE') {
        handleDelete($storage, $authResult, $isAdmin, $userId);
    } else {
        throw new ApiException('Method not allowed', 405);
    }

} catch (ApiException $e) {
    Response::error($e->getMessage(), $e->getCode());
} catch (\Exception $e) {
    Response::error('Internal server error', 500);
}

function handlePost(JsonStorage $storage, ?array $authResult, bool $isAdmin, string $userId): void
{
    $boardId = Input::getString('board_id');
    $cardId = Input::getString('card_id');
    $action = Input::getString('action');
    
    if (!$boardId || !$cardId) {
        throw new ApiException('Board ID and card ID required', 400);
    }

    // Check permissions
    if (!$isAdmin) {
        $permissions = new Permissions($storage, $authResult);
        if (!$permissions->canEditBoard($boardId)) {
            throw new ApiException('Forbidden', 403);
        }
    }

    $commentService = new CommentService($storage);

    switch ($action) {
        case 'create':
            $text = Input::getString('text');
            
            if (!$text) {
                throw new ApiException('Comment text required', 400);
            }

            $result = $commentService->create($boardId, $cardId, $userId, $text);
            Response::success($result['data'], $result['message']);
            break;

        case 'update':
            $commentId = Input::getString('comment_id');
            $text = Input::getString('text');

            if (!$commentId || !$text) {
                throw new ApiException('Comment ID and text required', 400);
            }

            $result = $commentService->update($boardId, $cardId, $commentId, $userId, $text, $isAdmin);
            Response::success($result['data'], $result['message']);
            break;

        default:
            throw new ApiException('Invalid action', 400);
    }
}

function handleDelete(JsonStorage $storage, ?array $authResult, bool $isAdmin, string $userId): void
{
    // Parse DELETE body
    parse_str(file_get_contents('php://input'), $postData);
    
    $boardId = $postData['board_id'] ?? '';
    $cardId = $postData['card_id'] ?? '';
    $commentId = $postData['comment_id'] ?? '';

    if (!$boardId || !$cardId || !$commentId) {
        throw new ApiException('Board ID, card ID, and comment ID required', 400);
    }

    if (!$isAdmin) {
        $permissions = new Permissions($storage, $authResult);
        if (!$permissions->canEditBoard($boardId)) {
            throw new ApiException('Forbidden', 403);
        }
    }

    $commentService = new CommentService($storage);
    $result = $commentService->delete($boardId, $cardId, $commentId, $userId, $isAdmin);
    
    Response::success($result['data'], $result['message']);
}
