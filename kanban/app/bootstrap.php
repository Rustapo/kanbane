<?php
/**
 * Bootstrap - инициализация приложения
 */

declare(strict_types=1);

// Загрузка конфигурации
$config = require __DIR__ . '/../config/config.php';

// Определение констант путей
define('ROOT_DIR', $config['root_dir']);
define('DATA_DIR', $config['data_dir']);
define('USERS_DIR', $config['users_dir']);
define('BOARDS_DIR', $config['boards_dir']);
define('HISTORY_DIR', $config['history_dir']);
define('BACKUPS_DIR', $config['backups_dir']);

// Настройки безопасности
define('SCHEMA_VERSION', $config['schema_version']);
define('APP_VERSION', $config['application_version']);
define('SESSION_LIFETIME', $config['session_lifetime']);
define('SESSION_NAME', $config['session_name']);
define('POLLING_INTERVAL', $config['polling_interval']);

// Лимиты
define('MAX_TITLE_LENGTH', $config['max_title_length']);
define('MAX_DESCRIPTION_LENGTH', $config['max_description_length']);
define('MAX_COMMENT_LENGTH', $config['max_comment_length']);
define('MAX_CHECKLIST_ITEMS', $config['max_checklist_items']);
define('MAX_TAGS_PER_CARD', $config['max_tags_per_card']);
define('MAX_ASSIGNEES_PER_CARD', $config['max_assignees_per_card']);

// Security
define('CSRF_TOKEN_LENGTH', $config['csrf_token_length']);
define('USER_ID_LENGTH', $config['user_id_length']);
define('BOARD_ID_LENGTH', $config['board_id_length']);
define('CARD_ID_LENGTH', $config['card_id_length']);
define('TOKEN_HASH_ALGORITHM', $config['token_hash_algorithm']);

// Отключение отображения ошибок в production
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Настройка сессии
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);

// Автозагрузка классов
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Проверка и инициализация структуры данных
function initializeDataStructure(): void {
    $directories = [
        DATA_DIR,
        USERS_DIR,
        BOARDS_DIR,
        HISTORY_DIR,
        BACKUPS_DIR,
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // Создание .htaccess для защиты data/
    $htaccessPath = DATA_DIR . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        file_put_contents($htaccessPath, "Deny from all\n");
    }

    // Инициализация system.json если отсутствует
    $systemFile = DATA_DIR . '/system.json';
    if (!file_exists($systemFile)) {
        $systemData = [
            'schema_version' => SCHEMA_VERSION,
            'application_version' => APP_VERSION,
            'created_at' => date('c'),
            'settings' => [
                'default_theme' => 'system',
            ],
            'admin' => null,
            'global_tags' => [],
        ];
        file_put_contents($systemFile, json_encode($systemData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

initializeDataStructure();

// Подключение вспомогательных функций
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/response.php';
