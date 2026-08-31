<?php

namespace App\Services\AI\Providers;

class GroqGptOssProvider extends OpenAiCompatibleProvider
{
    public function name(): string
    {
        return 'groq_gpt_oss';
    }

    protected function configKey(): string
    {
        return 'groq_gpt_oss';
    }
}
