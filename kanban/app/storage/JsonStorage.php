<?php
/**
 * JsonStorage - централизованный слой хранения с блокировками
 */

declare(strict_types=1);

namespace App\Storage;

use Throwable;

class JsonStorage
{
    /**
     * Чтение JSON файла с shared lock
     */
    public function read(string $filePath): ?array
    {
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
     * Запись JSON файла с exclusive lock и atomic write
     * Следует паттерну: LOCK → READ → MODIFY → WRITE TEMP → RENAME → UNLOCK
     */
    public function write(string $filePath, array $data): bool
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Открываем файл для записи с exclusive lock
        $handle = fopen($filePath, 'c');
        if ($handle === false) {
            return false;
        }

        try {
            // Блокируем на время всей операции read-modify-write
            flock($handle, LOCK_EX);

            // Читаем текущее содержимое для проверки revision (если нужно)
            $currentContent = fread($handle, filesize($filePath) ?: 1);
            
            // Создаём временный файл
            $tempFile = $filePath . '.tmp.' . getmypid() . '.' . time();
            $tempHandle = fopen($tempFile, 'w');
            if ($tempHandle === false) {
                flock($handle, LOCK_UN);
                fclose($handle);
                return false;
            }

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                fclose($tempHandle);
                unlink($tempFile);
                flock($handle, LOCK_UN);
                fclose($handle);
                return false;
            }

            fwrite($tempHandle, $json);
            fflush($tempHandle);
            fclose($tempHandle);

            // Atomic rename
            if (!rename($tempFile, $filePath)) {
                unlink($tempFile);
                flock($handle, LOCK_UN);
                fclose($handle);
                return false;
            }

            flock($handle, LOCK_UN);
            fclose($handle);

            return true;
        } catch (Throwable $e) {
            if (isset($tempHandle)) {
                fclose($tempHandle);
            }
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
            flock($handle, LOCK_UN);
            fclose($handle);
            return false;
        }
    }

    /**
     * Атомарное обновление с проверкой revision
     * Возвращает false если revision не совпадает (conflict)
     */
    public function updateWithRevisionCheck(
        string $filePath,
        callable $modifier,
        int $expectedRevision
    ): array {
        $handle = fopen($filePath, 'c+');
        if ($handle === false) {
            return ['success' => false, 'error' => 'CANNOT_OPEN_FILE'];
        }

        try {
            flock($handle, LOCK_EX);

            // Чтение текущего состояния
            $currentContent = fread($handle, filesize($filePath) ?: 1);
            $data = json_decode($currentContent, true);
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                flock($handle, LOCK_UN);
                fclose($handle);
                return ['success' => false, 'error' => 'INVALID_JSON'];
            }

            // Проверка revision
            $currentRevision = $data['revision'] ?? 0;
            if ($currentRevision !== $expectedRevision) {
                flock($handle, LOCK_UN);
                fclose($handle);
                return [
                    'success' => false,
                    'error' => 'REVISION_MISMATCH',
                    'current_revision' => $currentRevision,
                    'expected_revision' => $expectedRevision,
                ];
            }

            // Применение модификатора
            $result = $modifier($data);
            if ($result === false) {
                flock($handle, LOCK_UN);
                fclose($handle);
                return ['success' => false, 'error' => 'MODIFICATION_FAILED'];
            }

            // Инкремент revision
            $data['revision'] = $currentRevision + 1;
            $data['updated_at'] = date('c');

            // Запись во временный файл
            $tempFile = $filePath . '.tmp.' . getmypid() . '.' . time();
            $tempHandle = fopen($tempFile, 'w');
            if ($tempHandle === false) {
                flock($handle, LOCK_UN);
                fclose($handle);
                return ['success' => false, 'error' => 'CANNOT_CREATE_TEMP'];
            }

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                fclose($tempHandle);
                unlink($tempFile);
                flock($handle, LOCK_UN);
                fclose($handle);
                return ['success' => false, 'error' => 'JSON_ENCODE_ERROR'];
            }

            fwrite($tempHandle, $json);
            fflush($tempHandle);
            fclose($tempHandle);

            // Atomic rename
            if (!rename($tempFile, $filePath)) {
                unlink($tempFile);
                flock($handle, LOCK_UN);
                fclose($handle);
                return ['success' => false, 'error' => 'RENAME_FAILED'];
            }

            flock($handle, LOCK_UN);
            fclose($handle);

            return [
                'success' => true,
                'new_revision' => $data['revision'],
                'data' => $data,
            ];
        } catch (Throwable $e) {
            if (isset($tempHandle)) {
                fclose($tempHandle);
            }
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
            flock($handle, LOCK_UN);
            fclose($handle);
            return ['success' => false, 'error' => 'UNEXPECTED_ERROR', 'message' => $e->getMessage()];
        }
    }

    /**
     * Получение текущей revision файла
     */
    public function getRevision(string $filePath): ?int
    {
        $data = $this->read($filePath);
        return $data !== null ? ($data['revision'] ?? 0) : null;
    }

    /**
     * Append строки в JSONL файл
     */
    public function appendJsonl(string $filePath, array $lineData): bool
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($filePath, 'a');
        if ($handle === false) {
            return false;
        }

        try {
            flock($handle, LOCK_EX);
            
            $lineData['timestamp'] = date('c');
            $line = json_encode($lineData, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            
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

    /**
     * Чтение последних N записей из JSONL файла
     */
    public function readJsonlLast(string $filePath, int $limit = 100): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        // Берём последние N записей
        $lastLines = array_slice($lines, -$limit);
        
        $result = [];
        foreach ($lastLines as $line) {
            $decoded = json_decode($line, true);
            if ($decoded !== null) {
                $result[] = $decoded;
            }
        }

        return $result;
    }
}
