<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

/**
 * Exception for validation failures in business logic.
 * Separate from Laravel's ValidationException to keep domain pure.
 */
class DomainValidationException extends Exception
{
    protected array $errors;

    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public static function withErrors(array $errors): self
    {
        return new self($errors);
    }
}
