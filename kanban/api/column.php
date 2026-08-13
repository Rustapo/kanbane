<?php

/**
 * Column API Endpoint
 * 
 * Handles column CRUD operations
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Auth\TokenAuth;
use App\Auth\AdminAuth;
use App\Auth\Permissions;
use App\Security\Security;
use App\Security\Input;
use App\Services\ColumnService;
use App\Storage\JsonStorage;
use App\Helpers\Response;
use App\Helpers\Exceptions\ApiException;

header('Content-Type: application/json');
Security::setApiHeaders();

try {
    $storage = new JsonStorage();
    $auth = new TokenAuth($storage);
    $adminAuth = new AdminAuth($storage);
    
    // Authenticate (token or admin session)
    $authResult = $auth->authenticateFromRequest();
    $isAdmin = false;
    $userId = null;
    $permissionLevel = null;
    
    if ($authResult) {
        $userId = $authResult['user_id'];
        $permissionLevel = $authResult['level'];
    } else {
        // Try admin auth
        if ($adminAuth->isAuthenticated()) {
            $isAdmin = true;
            $userId = 'admin';
            $permissionLevel = 'admin';
        } else {
            throw new ApiException('Unauthorized', 401);
        }
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = Input::getString('action', 'GET');
    
    // Route based on action for POST, or method for GET
    if ($method === 'GET') {
        handleGet($storage, $authResult, $isAdmin, $userId);
    } elseif ($method === 'POST') {
        handlePost($storage, $authResult, $isAdmin, $userId, $action);
    } else {
        throw new ApiException('Method not allowed', 405);
    }

} catch (ApiException $e) {
    Response::error($e->getMessage(), $e->getCode());
} catch (\Exception $e) {
    Response::error('Internal server error', 500);
}

function handleGet(JsonStorage $storage, ?array $authResult, bool $isAdmin, string $userId): void
{
    // GET requests are limited - columns are part of board data
    throw new ApiException('Use board endpoint to get columns', 400);
}

function handlePost(JsonStorage $storage, ?array $authResult, bool $isAdmin, string $userId, string $action): void
{
    $boardId = Input::getString('board_id');
    if (!$boardId) {
        throw new ApiException('Board ID required', 400);
    }

    // Check permissions
    if (!$isAdmin) {
        $permissions = new Permissions($storage, $authResult);
        if (!$permissions->canEditBoard($boardId)) {
            throw new ApiException('Forbidden', 403);
        }
    }

    $columnService = new ColumnService($storage);

    switch ($action) {
        case 'create':
            $title = Input::getString('title');
            $position = Input::getInt('position', 0);
            
            if (!$title) {
                throw new ApiException('Title required', 400);
            }

            $result = $columnService->create($boardId, $title, $position);
            Response::success($result['data'], $result['message']);
            break;

        case 'update':
            $columnId = Input::getString('column_id');
            $updates = [];
            
            if (isset($_POST['title'])) {
                $updates['title'] = Input::getString('title');
            }

            if (!$columnId) {
                throw new ApiException('Column ID required', 400);
            }

            $result = $columnService->update($boardId, $columnId, $updates);
            Response::success($result['data'], $result['message']);
            break;

        case 'move':
            $columnIds = Input::getJsonArray('column_ids');
            
            if (!$columnIds || !is_array($columnIds)) {
                throw new ApiException('Column IDs array required', 400);
            }

            $result = $columnService->move($boardId, $columnIds);
            Response::success($result['data'], $result['message']);
            break;

        case 'archive':
            $columnId = Input::getString('column_id');
            $targetColumnId = Input::getString('target_column_id');

            if (!$columnId || !$targetColumnId) {
                throw new ApiException('Column ID and target column ID required', 400);
            }

            $result = $columnService->archive($boardId, $columnId, $targetColumnId);
            Response::success($result['data'], $result['message']);
            break;

        default:
            throw new ApiException('Invalid action', 400);
    }
}
