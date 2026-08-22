<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class FaceVerificationException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
