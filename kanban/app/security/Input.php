<?php
/**
 * Input - валидация входных данных
 */

declare(strict_types=1);

namespace App\Security;

class Input
{
    /**
     * Получение и санитизация строкового значения из GET
     */
    public static function get(string $key, string $default = ''): string
    {
        if (!isset($_GET[$key])) {
            return $default;
        }
        return self::sanitize($_GET[$key]);
    }

    /**
     * Получение и санитизация строкового значения из POST
     */
    public static function post(string $key, string $default = ''): string
    {
        if (!isset($_POST[$key])) {
            return $default;
        }
        return self::sanitize($_POST[$key]);
    }

    /**
     * Получение JSON из POST body
     */
    public static function json(): ?array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') === false) {
            return null;
        }

        $rawInput = file_get_contents('php://input');
        if ($rawInput === false || $rawInput === '') {
            return null;
        }

        $data = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Санитизация строки
     */
    public static function sanitize(string $value): string
    {
        return trim(strip_tags($value));
    }

    /**
     * Валидация обязательного поля
     */
    public static function requireField(?array $data, string $field, string $errorMessage = 'Field is required'): array
    {
        if ($data === null || !isset($data[$field]) || $data[$field] === '') {
            return ['valid' => false, 'error' => $errorMessage];
        }
        return ['valid' => true, 'value' => $data[$field]];
    }

    /**
     * Валидация длины строки
     */
    public static function validateLength(string $value, int $min, int $max, string $fieldName): array
    {
        $length = mb_strlen($value);
        if ($length < $min) {
            return ['valid' => false, 'error' => "{$fieldName} must be at least {$min} characters"];
        }
        if ($length > $max) {
            return ['valid' => false, 'error' => "{$fieldName} must not exceed {$max} characters"];
        }
        return ['valid' => true];
    }

    /**
     * Валидация ID
     */
    public static function validateId(string $id, int $expectedLength = 12, string $fieldName = 'ID'): array
    {
        if (!Security::isValidId($id, $expectedLength)) {
            return ['valid' => false, 'error' => "Invalid {$fieldName} format"];
        }
        return ['valid' => true];
    }

    /**
     * Валидация priority
     */
    public static function validatePriority(string $priority): array
    {
        $allowed = ['low', 'medium', 'high', 'critical'];
        if (!in_array(strtolower($priority), $allowed, true)) {
            return ['valid' => false, 'error' => 'Invalid priority value'];
        }
        return ['valid' => true, 'value' => strtolower($priority)];
    }

    /**
     * Валидация статуса
     */
    public static function validateStatus(string $status): array
    {
        $allowed = ['active', 'archived'];
        if (!in_array(strtolower($status), $allowed, true)) {
            return ['valid' => false, 'error' => 'Invalid status value'];
        }
        return ['valid' => true, 'value' => strtolower($status)];
    }

    /**
     * Валидация даты (ISO 8601)
     */
    public static function validateDate(?string $date, string $fieldName = 'Date'): array
    {
        if ($date === null || $date === '') {
            return ['valid' => true, 'value' => null];
        }

        $parsed = strtotime($date);
        if ($parsed === false) {
            return ['valid' => false, 'error' => "Invalid {$fieldName} format"];
        }

        return ['valid' => true, 'value' => date('c', $parsed)];
    }

    /**
     * Валидация целого числа в диапазоне
     */
    public static function validateInteger(int $value, int $min, int $max, string $fieldName): array
    {
        if ($value < $min || $value > $max) {
            return ['valid' => false, 'error' => "{$fieldName} must be between {$min} and {$max}"];
        }
        return ['valid' => true];
    }
}
