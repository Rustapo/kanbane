<?php
/**
 * Security - базовые функции безопасности
 */

declare(strict_types=1);

namespace App\Security;

class Security
{
    /**
     * Установка security headers для всех ответов
     */
    public static function setHeaders(): void
    {
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    /**
     * Проверка пути на path traversal атаки
     */
    public static function isSafePath(string $path): bool
    {
        if (strpos($path, '..') !== false) {
            return false;
        }
        if (strpos($path, '/') !== false || strpos($path, '\\') !== false) {
            return false;
        }
        if (preg_match('/[<>:"|?*]/', $path)) {
            return false;
        }
        return true;
    }

    /**
     * HTML escaping для защиты от XSS
     */
    public static function escapeHtml(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Очистка строки от тегов
     */
    public static function sanitizeString(string $str): string
    {
        return trim(strip_tags($str));
    }

    /**
     * Валидация ID (формат и длина)
     */
    public static function isValidId(string $id, int $expectedLength = 12): bool
    {
        if (strlen($id) !== $expectedLength) {
            return false;
        }
        return preg_match('/^[a-zA-Z0-9_-]+$/', $id) === 1;
    }

    /**
     * Валидация action из whitelist
     */
    public static function isValidAction(string $action, array $allowedActions): bool
    {
        return in_array($action, $allowedActions, true);
    }

    /**
     * Генерация CSRF токена
     */
    public static function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        
        return $_SESSION['csrf_token'];
    }

    /**
     * Проверка CSRF токена
     */
    public static function verifyCsrfToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Регенерация CSRF токена
     */
    public static function regenerateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        return $_SESSION['csrf_token'];
    }
}
