<?php
/**
 * API endpoint для работы с пользователями
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Security\Security;
use App\Security\Input;
use App\Auth\AdminAuth;
use App\Services\UserService;

// Установка security headers
Security::setHeaders();

$userService = new UserService();

// Определение метода запроса
$method = $_SERVER['REQUEST_METHOD'];
$action = Input::get('action', '');

// Получение данных из тела запроса для POST
$jsonData = null;
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    if ($rawInput !== false && $rawInput !== '') {
        $jsonData = json_decode($rawInput, true);
    }
}

try {
    // Обработка GET запросов (read operations)
    if ($method === 'GET') {
        handleGet($action, $userService);
    }

    // Обработка POST запросов (write operations)
    if ($method === 'POST') {
        handlePost($action, $userService, $jsonData);
    }

    // Метод не поддерживается
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'METHOD_NOT_ALLOWED']);
} catch (NotFoundException $e) {
    errorResponse('NOT_FOUND', $e->getMessage(), [], $e->getCode());
} catch (ConflictException $e) {
    conflictResponse($e->getMessage(), $e->getRevision());
} catch (ForbiddenException $e) {
    forbiddenResponse($e->getMessage());
} catch (UnauthorizedException $e) {
    unauthorizedResponse($e->getMessage());
} catch (ValidationException $e) {
    errorResponse('VALIDATION_ERROR', $e->getMessage(), ['errors' => $e->getErrors()], 422);
} catch (Throwable $e) {
    error_log('User API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    serverErrorResponse('An unexpected error occurred');
}

function handleGet(string $action, UserService $userService): void
{
    switch ($action) {
        case 'list':
            // Только admin может получить список всех пользователей
            if (!AdminAuth::isLoggedIn()) {
                forbiddenResponse('Admin access required');
            }
            $users = $userService->getAllActiveUsers();
            successResponse(['users' => $users]);
            break;

        case 'get':
            $userId = Input::get('user_id');
            if (!Security::isValidId($userId, USER_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid user ID', [], 400);
            }
            
            $user = $userService->getUser($userId);
            if ($user === null) {
                notFoundResponse('User not found');
            }
            successResponse(['user' => $user]);
            break;

        case 'boards':
            $userId = Input::get('user_id');
            if (!Security::isValidId($userId, USER_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid user ID', [], 400);
            }
            
            $boards = $userService->getUserBoards($userId);
            successResponse(['boards' => $boards]);
            break;

        default:
            errorResponse('INVALID_ACTION', 'Unknown action', [], 400);
    }
}

function handlePost(string $action, UserService $userService, ?array $jsonData): void
{
    if ($jsonData === null && in_array($action, ['create', 'update', 'add_to_board'])) {
        errorResponse('INVALID_JSON', 'Invalid JSON data', [], 400);
    }

    switch ($action) {
        case 'create':
            // Только admin может создавать пользователей
            if (!AdminAuth::isLoggedIn()) {
                forbiddenResponse('Admin access required');
            }

            $name = $jsonData['name'] ?? '';
            $validation = Input::validateLength($name, 1, 100, 'Name');
            if (!$validation['valid']) {
                errorResponse('VALIDATION_ERROR', $validation['error'], [], 422);
            }

            $user = $userService->createUser($name);
            successResponse(['user' => $user], 201);
            break;

        case 'update':
            if (!AdminAuth::isLoggedIn()) {
                forbiddenResponse('Admin access required');
            }

            $userId = $jsonData['user_id'] ?? '';
            if (!Security::isValidId($userId, USER_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid user ID', [], 400);
            }

            $updates = [];
            if (isset($jsonData['name'])) {
                $validation = Input::validateLength($jsonData['name'], 1, 100, 'Name');
                if (!$validation['valid']) {
                    errorResponse('VALIDATION_ERROR', $validation['error'], [], 422);
                }
                $updates['name'] = $jsonData['name'];
            }
            if (isset($jsonData['status'])) {
                $validStatuses = ['active', 'archived'];
                if (!in_array($jsonData['status'], $validStatuses)) {
                    errorResponse('VALIDATION_ERROR', 'Invalid status value', [], 422);
                }
                $updates['status'] = $jsonData['status'];
            }

            $actorId = 'admin'; // Admin выполняет действие
            $user = $userService->updateUser($userId, $updates, $actorId);
            successResponse(['user' => $user]);
            break;

        case 'archive':
            if (!AdminAuth::isLoggedIn()) {
                forbiddenResponse('Admin access required');
            }

            $userId = $jsonData['user_id'] ?? '';
            if (!Security::isValidId($userId, USER_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid user ID', [], 400);
            }

            $userService->archiveUser($userId, 'admin');
            successResponse(['archived' => true]);
            break;

        case 'restore':
            if (!AdminAuth::isLoggedIn()) {
                forbiddenResponse('Admin access required');
            }

            $userId = $jsonData['user_id'] ?? '';
            if (!Security::isValidId($userId, USER_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid user ID', [], 400);
            }

            $userService->restoreUser($userId, 'admin');
            successResponse(['restored' => true]);
            break;

        case 'add_to_board':
            if (!AdminAuth::isLoggedIn()) {
                forbiddenResponse('Admin access required');
            }

            $userId = $jsonData['user_id'] ?? '';
            $boardId = $jsonData['board_id'] ?? '';
            $permission = $jsonData['permission'] ?? 'view';

            if (!Security::isValidId($userId, USER_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid user ID', [], 400);
            }
            if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid board ID', [], 400);
            }

            $validPermissions = ['view', 'edit'];
            if (!in_array($permission, $validPermissions)) {
                errorResponse('VALIDATION_ERROR', 'Invalid permission value', [], 422);
            }

            $userService->addUserToBoard($userId, $boardId, $permission, 'admin');
            successResponse(['added' => true]);
            break;

        case 'remove_from_board':
            if (!AdminAuth::isLoggedIn()) {
                forbiddenResponse('Admin access required');
            }

            $userId = $jsonData['user_id'] ?? '';
            $boardId = $jsonData['board_id'] ?? '';

            if (!Security::isValidId($userId, USER_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid user ID', [], 400);
            }
            if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid board ID', [], 400);
            }

            $userService->removeUserFromBoard($userId, $boardId, 'admin');
            successResponse(['removed' => true]);
            break;

        default:
            errorResponse('INVALID_ACTION', 'Unknown action', [], 400);
    }
}
