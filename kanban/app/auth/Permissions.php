<?php
/**
 * Permissions - проверка прав доступа
 */

declare(strict_types=1);

namespace App\Auth;

class Permissions
{
    /**
     * Уровни доступа
     */
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const ADMIN = 'admin';

    private ?array $auth;

    public function __construct(?array $auth = null)
    {
        $this->auth = $auth;
    }

    /**
     * Проверка наличия любого уровня доступа
     */
    public function isLoggedIn(): bool
    {
        return $this->auth !== null;
    }

    /**
     * Проверка права VIEW
     */
    public function canView(): bool
    {
        if ($this->auth === null) {
            return false;
        }
        return true; // Любой аутентифицированный пользователь может просматривать
    }

    /**
     * Проверка права EDIT
     */
    public function canEdit(): bool
    {
        if ($this->auth === null) {
            return false;
        }
        return ($this->auth['role'] ?? '') === self::EDIT;
    }

    /**
     * Проверка права ADMIN
     */
    public function canAdmin(): bool
    {
        if ($this->auth === null) {
            return false;
        }
        return ($this->auth['role'] ?? '') === self::ADMIN;
    }

    /**
     * Проверка конкретного уровня доступа (с учётом иерархии)
     */
    public function hasAccess(string $requiredLevel): bool
    {
        if ($this->auth === null) {
            return false;
        }

        $hierarchy = [
            self::VIEW => 1,
            self::EDIT => 2,
            self::ADMIN => 3,
        ];

        $userLevel = $hierarchy[$this->auth['role'] ?? ''] ?? 0;
        $requiredLevelNum = $hierarchy[$requiredLevel] ?? 0;

        return $userLevel >= $requiredLevelNum;
    }

    /**
     * Получение user_id
     */
    public function getUserId(): ?string
    {
        return $this->auth['user_id'] ?? null;
    }

    /**
     * Получение board_id
     */
    public function getBoardId(): ?string
    {
        return $this->auth['board_id'] ?? null;
    }

    /**
     * Получение роли
     */
    public function getRole(): ?string
    {
        return $this->auth['role'] ?? null;
    }

    /**
     * Проверка что пользователь является членом доски
     */
    public function isBoardMember(string $boardId): bool
    {
        if ($this->auth === null) {
            return false;
        }
        return ($this->auth['board_id'] ?? '') === $boardId;
    }
}
