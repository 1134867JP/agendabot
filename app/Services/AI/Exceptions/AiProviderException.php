<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?int $status = null,
        public readonly ?string $errorType = null,
        public readonly bool $fallbackAllowed = false,
    ) {
        parent::__construct($message);
    }
}
