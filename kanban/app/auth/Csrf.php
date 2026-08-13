<?php
/**
 * Csrf - CSRF защита
 */

declare(strict_types=1);

namespace App\Auth;

class Csrf
{
    /**
     * Генерация CSRF токена
     */
    public static function generateToken(): string
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
    public static function verify(string $token): bool
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
    public static function regenerate(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        return $_SESSION['csrf_token'];
    }

    /**
     * Получение CSRF токена для формы
     */
    public static function tokenField(): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Проверка CSRF токена из запроса
     * Выбрасывает исключение или возвращает false при неудаче
     */
    public static function validateRequest(?string $token = null): bool
    {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }

        if ($token === '') {
            return false;
        }

        return self::verify($token);
    }

    /**
     * Требовать валидный CSRF токен (для API)
     */
    public static function requireValid(?string $token = null): void
    {
        if (!self::validateRequest($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'CSRF_INVALID',
                'message' => 'Invalid or missing CSRF token',
            ]);
            exit;
        }
    }
}
