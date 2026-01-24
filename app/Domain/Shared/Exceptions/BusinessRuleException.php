<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

/**
 * Exception for business rule violations.
 * Should be caught and transformed to appropriate HTTP responses.
 */
class BusinessRuleException extends Exception
{
    protected array $errors;

    public function __construct(string $message, array $errors = [], int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function withErrors(string $message, array $errors): self
    {
        return new self($message, $errors);
    }
}
