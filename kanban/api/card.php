<?php
/**
 * API endpoint для работы с карточками
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Security\Security;
use App\Security\Input;
use App\Auth\TokenAuth;
use App\Services\CardService;

// Установка security headers
Security::setHeaders();

$cardService = new CardService();

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
        handleGet($action, $cardService);
    }

    // Обработка POST запросов (write operations)
    if ($method === 'POST') {
        handlePost($action, $cardService, $jsonData);
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
    error_log('Card API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    serverErrorResponse('An unexpected error occurred');
}

function handleGet(string $action, CardService $cardService): void
{
    switch ($action) {
        case 'get':
            $boardId = Input::get('board_id');
            $cardId = Input::get('card_id');
            
            if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid board ID', [], 400);
            }
            if (!Security::isValidId($cardId, CARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid card ID', [], 400);
            }

            // Для получения карточки нужно получить всю доску и найти карточку
            // В реальной реализации можно оптимизировать
            errorResponse('NOT_IMPLEMENTED', 'Use board API to get cards', [], 501);
            break;

        default:
            errorResponse('INVALID_ACTION', 'Unknown action', [], 400);
    }
}

function handlePost(string $action, CardService $cardService, ?array $jsonData): void
{
    if ($jsonData === null && in_array($action, ['create', 'update', 'move'])) {
        errorResponse('INVALID_JSON', 'Invalid JSON data', [], 400);
    }

    switch ($action) {
        case 'create':
            $boardId = $jsonData['board_id'] ?? '';
            $columnId = $jsonData['column_id'] ?? '';
            $title = $jsonData['title'] ?? '';
            $userId = $jsonData['user_id'] ?? '';

            if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid board ID', [], 400);
            }
            if (!Security::isValidId($columnId, 12)) {
                errorResponse('INVALID_ID', 'Invalid column ID', [], 400);
            }

            $validation = Input::validateLength($title, 1, MAX_TITLE_LENGTH, 'Title');
            if (!$validation['valid']) {
                errorResponse('VALIDATION_ERROR', $validation['error'], [], 422);
            }

            $card = $cardService->createCard($boardId, $columnId, $title, $userId);
            successResponse(['card' => $card], 201);
            break;

        case 'update':
            $boardId = $jsonData['board_id'] ?? '';
            $cardId = $jsonData['card_id'] ?? '';
            $expectedRevision = (int)($jsonData['expected_revision'] ?? 0);
            $userId = $jsonData['user_id'] ?? '';

            if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid board ID', [], 400);
            }
            if (!Security::isValidId($cardId, CARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid card ID', [], 400);
            }

            $updates = [];
            if (isset($jsonData['title'])) {
                $updates['title'] = $jsonData['title'];
            }
            if (isset($jsonData['description'])) {
                $updates['description'] = $jsonData['description'];
            }
            if (isset($jsonData['priority'])) {
                $validPriorities = ['Low', 'Medium', 'High', 'Critical'];
                if (!in_array($jsonData['priority'], $validPriorities)) {
                    errorResponse('VALIDATION_ERROR', 'Invalid priority value', [], 422);
                }
                $updates['priority'] = $jsonData['priority'];
            }
            if (isset($jsonData['due_date'])) {
                $updates['due_date'] = $jsonData['due_date'];
            }
            if (isset($jsonData['tags'])) {
                $updates['tags'] = $jsonData['tags'];
            }
            if (isset($jsonData['assignees'])) {
                $updates['assignees'] = $jsonData['assignees'];
            }

            $card = $cardService->updateCard($boardId, $cardId, $updates, $expectedRevision, $userId);
            successResponse(['card' => $card]);
            break;

        case 'move':
            $boardId = $jsonData['board_id'] ?? '';
            $cardId = $jsonData['card_id'] ?? '';
            $targetColumnId = $jsonData['target_column_id'] ?? '';
            $targetPosition = (int)($jsonData['target_position'] ?? 0);
            $expectedRevision = (int)($jsonData['expected_revision'] ?? 0);
            $userId = $jsonData['user_id'] ?? '';

            if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid board ID', [], 400);
            }
            if (!Security::isValidId($cardId, CARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid card ID', [], 400);
            }
            if (!Security::isValidId($targetColumnId, 12)) {
                errorResponse('INVALID_ID', 'Invalid target column ID', [], 400);
            }

            $cardService->moveCard($boardId, $cardId, $targetColumnId, $targetPosition, $expectedRevision, $userId);
            successResponse(['moved' => true]);
            break;

        case 'archive':
            $boardId = $jsonData['board_id'] ?? '';
            $cardId = $jsonData['card_id'] ?? '';
            $userId = $jsonData['user_id'] ?? '';

            if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid board ID', [], 400);
            }
            if (!Security::isValidId($cardId, CARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid card ID', [], 400);
            }

            $cardService->archiveCard($boardId, $cardId, $userId);
            successResponse(['archived' => true]);
            break;

        case 'restore':
            $boardId = $jsonData['board_id'] ?? '';
            $cardId = $jsonData['card_id'] ?? '';
            $userId = $jsonData['user_id'] ?? '';

            if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid board ID', [], 400);
            }
            if (!Security::isValidId($cardId, CARD_ID_LENGTH)) {
                errorResponse('INVALID_ID', 'Invalid card ID', [], 400);
            }

            $cardService->restoreCard($boardId, $cardId, $userId);
            successResponse(['restored' => true]);
            break;

        default:
            errorResponse('INVALID_ACTION', 'Unknown action', [], 400);
    }
}
