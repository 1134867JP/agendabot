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

    /**
     * O Workers AI valida content como string obrigatória e rejeita com 400 a
     * mensagem do assistente que só chama ferramentas (content null no formato OpenAI).
     */
    protected function ajustarPayload(array $payload): array
    {
        foreach ($payload['messages'] ?? [] as $i => $message) {
            if (array_key_exists('content', $message) && $message['content'] === null) {
                $payload['messages'][$i]['content'] = '';
            }
        }

        return $payload;
    }
}
