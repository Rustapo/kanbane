<?php
/**
 * Исключения приложения
 */

declare(strict_types=1);

class NotFoundException extends Exception
{
    public function __construct(string $message = 'Not Found')
    {
        parent::__construct($message, 404);
    }
}

class ConflictException extends Exception
{
    private int $revision;

    public function __construct(string $message = 'Conflict', int $revision = 0)
    {
        parent::__construct($message, 409);
        $this->revision = $revision;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }
}

class ForbiddenException extends Exception
{
    public function __construct(string $message = 'Forbidden')
    {
        parent::__construct($message, 403);
    }
}

class UnauthorizedException extends Exception
{
    public function __construct(string $message = 'Unauthorized')
    {
        parent::__construct($message, 401);
    }
}

class ValidationException extends Exception
{
    private array $errors;

    public function __construct(array $errors = [])
    {
        $message = 'Validation failed';
        if (!empty($errors)) {
            $message = implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($errors), $errors));
        }
        parent::__construct($message, 422);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
