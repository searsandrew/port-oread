<?php

namespace App\Exceptions;

use Exception;

class TiberApiException extends Exception
{
    public function __construct(string $message, protected int $status = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function isServerError(): bool
    {
        return $this->status >= 500 || $this->status === 0;
    }

    public function isValidationError(): bool
    {
        return $this->status === 422;
    }
}
