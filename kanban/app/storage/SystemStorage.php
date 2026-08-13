<?php
/**
 * SystemStorage - Хранилище системных настроек
 */

class SystemStorage extends JsonStorage
{
    private const FILE = 'system.json';

    /**
     * Получить системные настройки
     */
    public function getSystemConfig(): ?array
    {
        $data = $this->read(self::FILE);
        return $data ?: null;
    }

    /**
     * Сохранить системные настройки
     */
    public function saveSystemConfig(array $config): bool
    {
        return $this->write(self::FILE, $config);
    }

    /**
     * Инициализировать системный файл при первом запуске
     */
    public function initializeSystem(): array
    {
        $now = date('c');
        
        $config = [
            'schema_version' => 1,
            'application_version' => '1.0.0',
            'created_at' => $now,
            'updated_at' => $now,
            'settings' => [
                'polling_interval' => 3000,
                'session_lifetime' => 3600,
                'max_file_size' => 10485760, // 10MB
                'allowed_extensions' => ['json', 'jsonl', 'zip']
            ],
            'admin' => [
                'password_hash' => null,
                'token_hash' => null,
                'created_at' => null,
                'last_login' => null
            ],
            'global_tags' => [],
            'secrets' => [
                'csrf_key' => bin2hex(random_bytes(32))
            ]
        ];

        $this->saveSystemConfig($config);
        return $config;
    }

    /**
     * Проверить, инициализирована ли система
     */
    public function isInitialized(): bool
    {
        $config = $this->getSystemConfig();
        return $config !== null && isset($config['schema_version']);
    }

    /**
     * Обновить системные настройки
     */
    public function updateSystemConfig(array $updates): array
    {
        return $this->update(self::FILE, function(&$current) use ($updates) {
            foreach ($updates as $key => $value) {
                if (is_array($value) && isset($current[$key]) && is_array($current[$key])) {
                    $current[$key] = array_merge($current[$key], $value);
                } else {
                    $current[$key] = $value;
                }
            }
            $current['updated_at'] = date('c');
            return $current;
        });
    }

    /**
     * Установить пароль администратора
     */
    public function setAdminPassword(string $password): bool
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        return $this->updateSystemConfig([
            'admin' => ['password_hash' => $hash]
        ]) !== null;
    }

    /**
     * Установить Admin token
     */
    public function setAdminToken(string $token): bool
    {
        $hash = hash('sha256', $token);
        return $this->updateSystemConfig([
            'admin' => ['token_hash' => $hash]
        ]) !== null;
    }

    /**
     * Добавить глобальный тег
     */
    public function addGlobalTag(string $tagId, string $name, string $color): bool
    {
        return $this->updateSystemConfig([
            'global_tags' => [
                $tagId => [
                    'id' => $tagId,
                    'name' => $name,
                    'color' => $color,
                    'status' => 'active'
                ]
            ]
        ]) !== null;
    }

    /**
     * Получить CSRF ключ
     */
    public function getCsrfKey(): string
    {
        $config = $this->getSystemConfig();
        return $config['secrets']['csrf_key'] ?? '';
    }
}
