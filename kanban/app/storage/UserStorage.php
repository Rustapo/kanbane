<?php
/**
 * UserStorage - Специализированное хранилище для пользователей
 */

class UserStorage extends JsonStorage
{
    private const DIR = 'users';

    /**
     * Получить данные пользователя
     */
    public function getUser(string $userId): ?array
    {
        $data = $this->read(self::DIR . '/' . $userId . '.json');
        return $data ?: null;
    }

    /**
     * Сохранить данные пользователя
     */
    public function saveUser(array $userData): bool
    {
        if (empty($userData['id'])) {
            throw new InvalidArgumentException('User ID is required');
        }
        return $this->write(self::DIR . '/' . $userData['id'] . '.json', $userData);
    }

    /**
     * Создать нового пользователя
     */
    public function createUser(array $data): array
    {
        $userId = $this->generateId('usr_');
        
        $now = date('c');
        $user = [
            'id' => $userId,
            'name' => $data['name'] ?? 'Anonymous',
            'status' => $data['status'] ?? 'active',
            'created_at' => $now,
            'updated_at' => $now
        ];

        $this->saveUser($user);
        return $user;
    }

    /**
     * Обновить пользователя
     */
    public function updateUser(string $userId, array $updates): array
    {
        $filePath = self::DIR . '/' . $userId . '.json';
        
        return $this->update($filePath, function(&$current) use ($updates, $userId) {
            foreach ($updates as $key => $value) {
                if ($key !== 'id' && $key !== 'created_at') {
                    $current[$key] = $value;
                }
            }
            $current['updated_at'] = date('c');
            return $current;
        });
    }

    /**
     * Получить всех активных пользователей
     */
    public function getAllActiveUsers(): array
    {
        $dirPath = $this->getBasePath() . '/' . self::DIR;
        if (!is_dir($dirPath)) {
            return [];
        }

        $users = [];
        $files = glob($dirPath . '/*.json');
        
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) continue;
            
            $data = json_decode($content, true);
            if ($data && ($data['status'] ?? '') === 'active') {
                $users[] = $data;
            }
        }

        return $users;
    }

    /**
     * Архивировать пользователя
     */
    public function archiveUser(string $userId): bool
    {
        return $this->updateUser($userId, ['status' => 'archived']);
    }

    /**
     * Восстановить пользователя
     */
    public function restoreUser(string $userId): bool
    {
        return $this->updateUser($userId, ['status' => 'active']);
    }
}
