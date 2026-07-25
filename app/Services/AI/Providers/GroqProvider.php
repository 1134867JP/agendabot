<?php

namespace App\Services\AI\Providers;

class GroqProvider extends OpenAiCompatibleProvider
{
    public function name(): string
    {
        return 'groq';
    }

    protected function configKey(): string
    {
        return 'groq';
    }
}
