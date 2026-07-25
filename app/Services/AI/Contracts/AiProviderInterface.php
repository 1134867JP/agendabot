<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTO\AiRequest;
use App\Services\AI\DTO\AiResponse;

interface AiProviderInterface
{
    public function name(): string;

    public function configured(): bool;

    public function chat(AiRequest $request): AiResponse;
}
