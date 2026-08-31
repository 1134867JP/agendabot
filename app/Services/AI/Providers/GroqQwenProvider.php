<?php

namespace App\Services\AI\Providers;

class GroqQwenProvider extends OpenAiCompatibleProvider
{
    public function name(): string
    {
        return 'groq_qwen';
    }

    protected function configKey(): string
    {
        return 'groq_qwen';
    }
}
