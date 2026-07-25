<?php

namespace App\Services\AI\Providers;

class OpenRouterProvider extends OpenAiCompatibleProvider
{
    public function name(): string
    {
        return 'openrouter';
    }

    protected function configKey(): string
    {
        return 'openrouter';
    }
}
