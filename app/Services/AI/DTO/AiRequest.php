<?php

namespace App\Services\AI\DTO;

final readonly class AiRequest
{
    public function __construct(
        public array $payload,
        public string $model,
    ) {}
}
