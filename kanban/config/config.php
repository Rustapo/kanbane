<?php
/**
 * Конфигурация приложения
 */

declare(strict_types=1);

return [
    // Пути
    'root_dir' => dirname(__DIR__),
    'data_dir' => dirname(__DIR__) . '/data',
    'users_dir' => dirname(__DIR__) . '/data/users',
    'boards_dir' => dirname(__DIR__) . '/data/boards',
    'history_dir' => dirname(__DIR__) . '/data/history',
    'backups_dir' => dirname(__DIR__) . '/data/backups',

    // Настройки сессии
    'session_lifetime' => 3600, // 1 час
    'session_name' => 'KANBAN_ADMIN',

    // Polling interval (ms)
    'polling_interval' => 3000,

    // Лимиты
    'max_title_length' => 200,
    'max_description_length' => 10000,
    'max_comment_length' => 2000,
    'max_checklist_items' => 50,
    'max_tags_per_card' => 20,
    'max_assignees_per_card' => 10,

    // Версия схемы
    'schema_version' => 1,
    'application_version' => '1.0.0',

    // Security
    'csrf_token_length' => 32,
    'user_id_length' => 12,
    'board_id_length' => 12,
    'card_id_length' => 12,
    'token_hash_algorithm' => 'sha256',
];
