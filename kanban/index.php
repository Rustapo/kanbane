<?php
/**
 * Главный entry point приложения
 */

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/app/bootstrap.php';

use App\Security\Security;
use App\Auth\AdminAuth;
use App\Storage\JsonStorage;

// Установка security headers
Security::setHeaders();

// Определение роутинга
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = dirname($_SERVER['SCRIPT_NAME']);

// Нормализация путей
$requestUri = str_replace($basePath, '', $requestUri);
$requestUri = trim($requestUri, '/');

// Разделение на части пути
$parts = explode('/', $requestUri);
$firstPart = $parts[0] ?? '';

// Обработка API запросов
if ($firstPart === 'api') {
    $apiFile = __DIR__ . '/api/' . ($parts[1] ?? '') . '.php';
    
    if (file_exists($apiFile)) {
        require $apiFile;
        exit;
    } else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'NOT_FOUND',
            'message' => 'API endpoint not found',
        ]);
        exit;
    }
}

// Обработка assets
if ($firstPart === 'assets') {
    $assetPath = __DIR__ . '/' . $requestUri;
    
    // Защита от path traversal
    $realPath = realpath($assetPath);
    $baseRealPath = realpath(__DIR__);
    
    if ($realPath === false || strpos($realPath, $baseRealPath) !== 0) {
        http_response_code(403);
        exit;
    }
    
    if (!file_exists($assetPath)) {
        http_response_code(404);
        exit;
    }
    
    // Определение Content-Type
    $extension = pathinfo($assetPath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
    ];
    
    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
    header("Content-Type: {$mimeType}");
    header('Cache-Control: public, max-age=31536000');
    
    readfile($assetPath);
    exit;
}

// Проверка необходимости установки Admin
$storage = new JsonStorage(DATA_DIR);
$adminAuth = new AdminAuth($storage);

if (!$adminAuth->isAdminSetup()) {
    // Перенаправление на setup
    if ($requestUri !== 'setup' && $requestUri !== '') {
        header('Location: /setup.php');
        exit;
    }
    require __DIR__ . '/views/setup.php';
    exit;
}

// Основная точка входа - отображение доски или списка досок
if ($requestUri === '' || $requestUri === 'index.php') {
    // Показываем список досок или перенаправляем на доску по токену
    $token = $_GET['token'] ?? null;
    
    if ($token !== null) {
        // Аутентификация по токену и перенаправление на доску
        // Логика будет в views/login.php или board.php
        require __DIR__ . '/views/login.php';
        exit;
    }
    
    // Показываем страницу входа/выбора доски
    require __DIR__ . '/views/login.php';
    exit;
}

// Доступ к конкретной доске
if (preg_match('/^board\/([a-zA-Z0-9_-]+)$/', $requestUri, $matches)) {
    $boardId = $matches[1];
    
    // Валидация board ID
    if (!Security::isValidId($boardId, BOARD_ID_LENGTH)) {
        http_response_code(404);
        exit;
    }
    
    // Проверка существования доски
    $boardFile = BOARDS_DIR . "/{$boardId}.json";
    if (!file_exists($boardFile)) {
        http_response_code(404);
        exit;
    }
    
    // Отображение доски (аутентификация будет внутри)
    require __DIR__ . '/views/board.php';
    exit;
}

// Admin панель
if ($requestUri === 'admin') {
    require __DIR__ . '/views/admin.php';
    exit;
}

// Страница не найдена
http_response_code(404);
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>404 Not Found</title></head><body>';
echo '<h1>404 Not Found</h1>';
echo '<p>The requested page was not found.</p>';
echo '</body></html>';
