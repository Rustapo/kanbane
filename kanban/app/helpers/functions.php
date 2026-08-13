<?php
/**
 * Вспомогательные функции
 */

declare(strict_types=1);

/**
 * Генерация криптографически случайного ID
 */
function generateId(int $length = 12): string {
    $bytes = random_bytes(ceil($length * 3 / 4));
    return substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, $length);
}

/**
 * Генерация криптографически случайного токена
 */
function generateToken(int $length = 32): string {
    $bytes = random_bytes($length);
    return bin2hex($bytes);
}

/**
 * Хэширование токена
 */
function hashToken(string $token): string {
    return hash(TOKEN_HASH_ALGORITHM, $token);
}

/**
 * Проверка токена
 */
function verifyToken(string $token, string $hash): bool {
    return hash_equals($hash, hashToken($token));
}

/**
 * Получение текущего timestamp в ISO 8601
 */
function currentTimestamp(): string {
    return date('c');
}

/**
 * Валидация ID
 */
function isValidId(string $id, int $expectedLength = 12): bool {
    if (strlen($id) !== $expectedLength) {
        return false;
    }
    return preg_match('/^[a-zA-Z0-9_-]+$/', $id) === 1;
}

/**
 * Очистка строки от опасных символов
 */
function sanitizeString(string $str): string {
    return trim(strip_tags($str));
}

/**
 * HTML escaping
 */
function escapeHtml(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Проверка пути на path traversal
 */
function isSafePath(string $path): bool {
    // Запрещаем любые path separators и navigation
    if (strpos($path, '..') !== false) {
        return false;
    }
    if (strpos($path, '/') !== false || strpos($path, '\\') !== false) {
        return false;
    }
    return true;
}

/**
 * Чтение JSON файла с блокировкой
 */
function readJsonFile(string $filePath): ?array {
    if (!file_exists($filePath)) {
        return null;
    }

    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        return null;
    }

    try {
        flock($handle, LOCK_SH);
        $content = fread($handle, filesize($filePath) ?: 1);
        flock($handle, LOCK_UN);
        fclose($handle);

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $data;
    } catch (Throwable $e) {
        fclose($handle);
        return null;
    }
}

/**
 * Запись JSON файла с блокировкой и atomic write
 */
function writeJsonFile(string $filePath, array $data): bool {
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $tempFile = $filePath . '.tmp.' . getmypid();
    
    $handle = fopen($tempFile, 'w');
    if ($handle === false) {
        return false;
    }

    try {
        flock($handle, LOCK_EX);
        
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            flock($handle, LOCK_UN);
            fclose($handle);
            unlink($tempFile);
            return false;
        }

        fwrite($handle, $json);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        if (!rename($tempFile, $filePath)) {
            unlink($tempFile);
            return false;
        }

        return true;
    } catch (Throwable $e) {
        flock($handle, LOCK_UN);
        fclose($handle);
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        return false;
    }
}

/**
 * Добавление записи в JSONL файл истории
 */
function appendHistory(string $boardId, array $event): bool {
    $historyFile = HISTORY_DIR . "/{$boardId}.jsonl";
    
    $event['timestamp'] = currentTimestamp();
    $line = json_encode($event, JSON_UNESCAPED_UNICODE) . PHP_EOL;

    $handle = fopen($historyFile, 'a');
    if ($handle === false) {
        return false;
    }

    try {
        flock($handle, LOCK_EX);
        fwrite($handle, $line);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        return true;
    } catch (Throwable $e) {
        fclose($handle);
        return false;
    }
}
