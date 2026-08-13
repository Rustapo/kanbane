<?php
/**
 * API endpoint для работы с досками
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Security\Security;
use App\Security\Input;
use App\Auth\Csrf;
use App\Auth\TokenAuth;
use App\Auth\AdminAuth;
use App\Storage\JsonStorage;
use App\Services\BoardService;

// Установка security headers
Security::setHeaders();

$storage = new JsonStorage(DATA_DIR);
$boardService = new BoardService($storage);

// Определение метода запроса
$method = $_SERVER['REQUEST_METHOD'];
$action = Input::get('action', '');

// Обработка GET запросов (read operations)
if ($method === 'GET') {
    handleGet($action, $boardService);
}

// Обработка POST запросов (write operations)
if ($method === 'POST') {
    handlePost($action, $boardService);
}

// Метод не поддерживается
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'METHOD_NOT_ALLOWED']);

function handleGet(string $action, BoardService $boardService): void
{
    switch ($action) {
        case 'list':
            // Список всех досок (для admin) или досок пользователя
            $boards = $boardService->getAllBoards();
            successResponse(['boards' => $boards]);
            break;

        case 'get':
            $boardId = Input::get('board_id');
            if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid board ID', [], 400);
            }
            $board = $boardService->getBoard($boardId);
            if ($board === null) {
                notFoundResponse('Board not found');
            }
            successResponse(['board' => $board]);
            break;

        case 'revision':
            $boardId = Input::get('board_id');
            if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid board ID', [], 400);
            }
            $revision = $boardService->getRevision($boardId);
            if ($revision === null) {
                notFoundResponse('Board not found');
            }
            successResponse(['revision' => $revision]);
            break;

        default:
            errorResponse('INVALID_ACTION', 'Unknown action', [], 400);
    }
}

function handlePost(string $action, BoardService $boardService): void
{
    // Проверка CSRF для мутаций
    $jsonData = Input::json();
    $csrfToken = $jsonData['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    
    // Для создания доски требуется admin или специальный токен
    // Здесь упрощённая проверка - в реальности нужна полная auth логика
    
    switch ($action) {
        case 'create':
            if ($jsonData === null) {
                errorResponse('INVALID_JSON', 'Invalid JSON data', [], 400);
            }
            
            Csrf::requireValid($csrfToken);
            
            $name = $jsonData['name'] ?? '';
            $description = $jsonData['description'] ?? '';
            
            $validation = Input::validateLength($name, 1, MAX_TITLE_LENGTH, 'Board name');
            if (!$validation['valid']) {
                errorResponse('VALIDATION_ERROR', $validation['error'], [], 422);
            }
            
            $result = $boardService->createBoard($name, $description);
            if ($result === null) {
                serverErrorResponse('Failed to create board');
            }
            
            successResponse(['board' => $result], 201);
            break;

        case 'update':
            if ($jsonData === null) {
                errorResponse('INVALID_JSON', 'Invalid JSON data', [], 400);
            }
            
            Csrf::requireValid($csrfToken);
            
            $boardId = $jsonData['board_id'] ?? '';
            $name = $jsonData['name'] ?? null;
            $description = $jsonData['description'] ?? null;
            
            if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid board ID', [], 400);
            }
            
            $result = $boardService->updateBoard($boardId, [
                'name' => $name,
                'description' => $description,
            ]);
            
            if ($result === false) {
                serverErrorResponse('Failed to update board');
            }
            
            successResponse(['board' => $result]);
            break;

        case 'archive':
            if ($jsonData === null) {
                errorResponse('INVALID_JSON', 'Invalid JSON data', [], 400);
            }
            
            Csrf::requireValid($csrfToken);
            
            $boardId = $jsonData['board_id'] ?? '';
            if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid board ID', [], 400);
            }
            
            $result = $boardService->archiveBoard($boardId);
            if ($result === false) {
                serverErrorResponse('Failed to archive board');
            }
            
            successResponse(['archived' => true]);
            break;

        default:
            errorResponse('INVALID_ACTION', 'Unknown action', [], 400);
    }
}
