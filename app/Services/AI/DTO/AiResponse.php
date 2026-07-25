<?php

namespace App\Services\AI\DTO;

final readonly class AiResponse
{
    public function __construct(
        public string $provider,
        public string $model,
        public array $content,
        public ?string $stopReason,
        public array $usage,
        public float $costUsd,
        public int $latencyMs,
        public ?string $requestId = null,
    ) {}
}
