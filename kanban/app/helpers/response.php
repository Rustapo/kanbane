<?php
/**
 * Функции для HTTP ответов
 */

declare(strict_types=1);

/**
 * Отправка JSON ответа
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Отправка успешного ответа
 */
function successResponse(array $data = [], int $statusCode = 200): void {
    jsonResponse([
        'success' => true,
        'data' => $data,
    ], $statusCode);
}

/**
 * Отправка ответа об ошибке
 */
function errorResponse(string $error, string $message, array $extra = [], int $statusCode = 400): void {
    $response = [
        'success' => false,
        'error' => $error,
        'message' => $message,
    ];
    
    if (!empty($extra)) {
        $response = array_merge($response, $extra);
    }
    
    jsonResponse($response, $statusCode);
}

/**
 * Отправка ответа 204 No Content
 */
function noContentResponse(): void {
    http_response_code(204);
    exit;
}

/**
 * Отправка ответа 401 Unauthorized
 */
function unauthorizedResponse(string $message = 'Unauthorized'): void {
    errorResponse('UNAUTHORIZED', $message, [], 401);
}

/**
 * Отправка ответа 403 Forbidden
 */
function forbiddenResponse(string $message = 'Forbidden'): void {
    errorResponse('FORBIDDEN', $message, [], 403);
}

/**
 * Отправка ответа 404 Not Found
 */
function notFoundResponse(string $message = 'Not Found'): void {
    errorResponse('NOT_FOUND', $message, [], 404);
}

/**
 * Отправка ответа 409 Conflict
 */
function conflictResponse(string $message, int $revision): void {
    errorResponse('CONFLICT', $message, ['revision' => $revision], 409);
}

/**
 * Отправка ответа 422 Unprocessable Entity
 */
function unprocessableResponse(string $message): void {
    errorResponse('UNPROCESSABLE_ENTITY', $message, [], 422);
}

/**
 * Отправка ответа 500 Internal Server Error
 */
function serverErrorResponse(string $message = 'Internal Server Error'): void {
    errorResponse('INTERNAL_ERROR', $message, [], 500);
}

/**
 * Установка security headers
 */
function setSecurityHeaders(): void {
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
