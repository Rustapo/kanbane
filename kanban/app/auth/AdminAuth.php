<?php
/**
 * AdminAuth - аутентификация администратора через сессию
 */

declare(strict_types=1);

namespace App\Auth;

use App\Storage\JsonStorage;

class AdminAuth
{
    private JsonStorage $storage;

    public function __construct(JsonStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Инициализация сессии
     */
    public function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
    }

    /**
     * Проверка, установлен ли admin пароль
     */
    public function isAdminSetup(): bool
    {
        $systemFile = DATA_DIR . '/system.json';
        $system = $this->storage->read($systemFile);
        
        return isset($system['admin']['password_hash']);
    }

    /**
     * Установка admin пароля (только при первой настройке)
     */
    public function setupAdmin(string $password): ?string
    {
        if ($this->isAdminSetup()) {
            return null;
        }

        $systemFile = DATA_DIR . '/system.json';
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Генерируем admin access token
        $rawToken = generateToken(32);
        $tokenHash = hashToken($rawToken);

        $result = $this->storage->updateWithRevisionCheck(
            $systemFile,
            function (array &$system) use ($passwordHash, $tokenHash) {
                $system['admin'] = [
                    'password_hash' => $passwordHash,
                    'created_at' => currentTimestamp(),
                    'tokens' => [
                        [
                            'id' => generateId(8),
                            'hash' => $tokenHash,
                            'status' => 'active',
                            'created_at' => currentTimestamp(),
                        ]
                    ],
                ];
                return true;
            },
            $system['revision'] ?? 0
        );

        if ($result['success']) {
            return $rawToken;
        }

        return null;
    }

    /**
     * Аутентификация по паролю
     */
    public function login(string $password): bool
    {
        $this->initSession();

        $systemFile = DATA_DIR . '/system.json';
        $system = $this->storage->read($systemFile);

        if (!isset($system['admin']['password_hash'])) {
            return false;
        }

        if (!password_verify($password, $system['admin']['password_hash'])) {
            return false;
        }

        // Regenerate session ID для защиты от session fixation
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_authenticated_at'] = time();

        return true;
    }

    /**
     * Проверка аутентификации администратора
     */
    public function isAuthenticated(): bool
    {
        $this->initSession();

        if (empty($_SESSION['admin_authenticated'])) {
            return false;
        }

        // Проверка timeout сессии
        $authenticatedAt = $_SESSION['admin_authenticated_at'] ?? 0;
        if (time() - $authenticatedAt > SESSION_LIFETIME) {
            $this->logout();
            return false;
        }

        return true;
    }

    /**
     * Logout администратора
     */
    public function logout(): void
    {
        $this->initSession();
        $_SESSION = [];
        
        if (ini_get('session.use_strict_mode')) {
            session_unset();
        }
        
        session_destroy();
    }

    /**
     * Проверка admin токена (для API)
     */
    public function authenticateToken(string $token): bool
    {
        $systemFile = DATA_DIR . '/system.json';
        $system = $this->storage->read($systemFile);

        if (!isset($system['admin']['tokens'])) {
            return false;
        }

        $tokenHash = hashToken($token);

        foreach ($system['admin']['tokens'] as $tokenData) {
            if (!isset($tokenData['hash'], $tokenData['status'])) {
                continue;
            }

            if ($tokenData['status'] !== 'active') {
                continue;
            }

            if (hash_equals($tokenData['hash'], $tokenHash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Генерация нового admin токена
     */
    public function generateToken(): ?string
    {
        $systemFile = DATA_DIR . '/system.json';
        $system = $this->storage->read($systemFile);

        if (!isset($system['admin'])) {
            return null;
        }

        $rawToken = generateToken(32);
        $tokenHash = hashToken($rawToken);

        $result = $this->storage->updateWithRevisionCheck(
            $systemFile,
            function (array &$system) use ($tokenHash) {
                $system['admin']['tokens'][] = [
                    'id' => generateId(8),
                    'hash' => $tokenHash,
                    'status' => 'active',
                    'created_at' => currentTimestamp(),
                ];
                return true;
            },
            $system['revision'] ?? 0
        );

        if ($result['success']) {
            return $rawToken;
        }

        return null;
    }

    /**
     * Отзыв admin токена
     */
    public function revokeToken(string $tokenId): bool
    {
        $systemFile = DATA_DIR . '/system.json';
        $system = $this->storage->read($systemFile);

        if (!isset($system['admin']['tokens'])) {
            return false;
        }

        $result = $this->storage->updateWithRevisionCheck(
            $systemFile,
            function (array &$system) use ($tokenId) {
                foreach ($system['admin']['tokens'] as &$tokenData) {
                    if ($tokenData['id'] === $tokenId) {
                        $tokenData['status'] = 'revoked';
                        $tokenData['revoked_at'] = currentTimestamp();
                        return true;
                    }
                }
                return false;
            },
            $system['revision'] ?? 0
        );

        return $result['success'] ?? false;
    }

    /**
     * Смена пароля администратора
     */
    public function changePassword(string $oldPassword, string $newPassword): bool
    {
        $this->initSession();

        $systemFile = DATA_DIR . '/system.json';
        $system = $this->storage->read($systemFile);

        if (!isset($system['admin']['password_hash'])) {
            return false;
        }

        // Проверка старого пароля
        if (!password_verify($oldPassword, $system['admin']['password_hash'])) {
            return false;
        }

        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $result = $this->storage->updateWithRevisionCheck(
            $systemFile,
            function (array &$system) use ($newPasswordHash) {
                $system['admin']['password_hash'] = $newPasswordHash;
                $system['admin']['password_changed_at'] = currentTimestamp();
                return true;
            },
            $system['revision'] ?? 0
        );

        return $result['success'] ?? false;
    }
}
