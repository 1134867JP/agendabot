<?php

namespace App\Services\AI\Providers;

class CloudflareProvider extends OpenAiCompatibleProvider
{
    public function name(): string
    {
        return 'cloudflare';
    }

    protected function configKey(): string
    {
        return 'cloudflare';
    }

    public function configured(): bool
    {
        return filled(config('ai.providers.cloudflare.key'))
            && filled(config('ai.providers.cloudflare.base_url'));
    }
}
