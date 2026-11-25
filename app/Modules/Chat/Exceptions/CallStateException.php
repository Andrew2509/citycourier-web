<?php

namespace App\Modules\Chat\Exceptions;

use RuntimeException;

class CallStateException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'CALL_ERROR',
        public readonly int $statusCode = 422
    ) {
        parent::__construct($message, $statusCode);
    }
}
