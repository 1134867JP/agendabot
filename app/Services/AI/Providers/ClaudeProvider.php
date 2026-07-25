<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\DTO\AiRequest;
use App\Services\AI\DTO\AiResponse;
use App\Services\AI\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ClaudeProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'claude';
    }

    public function configured(): bool
    {
        return filled(config('ai.providers.claude.key'));
    }

    public function chat(AiRequest $request): AiResponse
    {
        $startedAt = hrtime(true);

        try {
            $response = Http::timeout((int) config('ai.timeout_seconds', 30))
                ->withHeaders([
                    'x-api-key' => config('ai.providers.claude.key'),
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post(rtrim((string) config('ai.providers.claude.base_url'), '/').'/v1/messages', array_merge(
                    $request->payload,
                    ['model' => $request->model],
                ));
        } catch (ConnectionException $e) {
            throw new AiProviderException($e->getMessage(), $this->name(), fallbackAllowed: true);
        }

        if (! $response->successful()) {
            throw new AiProviderException(
                (string) ($response->json('error.message') ?: 'Falha na API da Anthropic.'),
                $this->name(),
                $response->status(),
                $response->json('error.type'),
                in_array($response->status(), config('ai.fallback_statuses', []), true),
            );
        }

        if (! is_array($response->json('content')) || $response->json('content') === []) {
            throw new AiProviderException(
                'Resposta vazia ou inválida da API Anthropic.',
                $this->name(),
                errorType: 'invalid_response',
                fallbackAllowed: true,
            );
        }

        return new AiResponse(
            provider: $this->name(),
            model: $request->model,
            content: $response->json('content', []),
            stopReason: $response->json('stop_reason'),
            usage: [
                'input_tokens' => (int) $response->json('usage.input_tokens', 0),
                'output_tokens' => (int) $response->json('usage.output_tokens', 0),
                'cache_creation_input_tokens' => (int) $response->json('usage.cache_creation_input_tokens', 0),
                'cache_read_input_tokens' => (int) $response->json('usage.cache_read_input_tokens', 0),
            ],
            costUsd: 0,
            latencyMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
            requestId: $response->header('request-id'),
        );
    }
}
