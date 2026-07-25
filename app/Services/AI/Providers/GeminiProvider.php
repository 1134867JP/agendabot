<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\DTO\AiRequest;
use App\Services\AI\DTO\AiResponse;
use App\Services\AI\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GeminiProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'gemini';
    }

    public function configured(): bool
    {
        return filled(config('ai.providers.gemini.key'));
    }

    public function chat(AiRequest $request): AiResponse
    {
        $startedAt = hrtime(true);
        $config = config('ai.providers.gemini');

        try {
            $response = Http::timeout((int) config('ai.timeout_seconds', 30))
                ->withHeaders(['x-goog-api-key' => $config['key']])
                ->acceptJson()
                ->post(
                    rtrim((string) $config['base_url'], '/').'/models/'.rawurlencode($request->model).':generateContent',
                    $this->toGeminiPayload($request->payload),
                );
        } catch (ConnectionException $e) {
            throw new AiProviderException($e->getMessage(), $this->name(), fallbackAllowed: true);
        }

        if (! $response->successful()) {
            throw new AiProviderException(
                (string) ($response->json('error.message') ?: 'Falha na API Gemini.'),
                $this->name(),
                $response->status(),
                $response->json('error.status'),
                in_array($response->status(), config('ai.fallback_statuses', []), true),
            );
        }

        $content = [];
        foreach ($response->json('candidates.0.content.parts', []) as $part) {
            if (isset($part['text'])) {
                $content[] = ['type' => 'text', 'text' => $part['text']];
            }
            if (isset($part['functionCall'])) {
                $content[] = [
                    'type' => 'tool_use',
                    'id' => $part['functionCall']['id'] ?? 'gemini_'.Str::uuid(),
                    'name' => $part['functionCall']['name'],
                    'input' => $part['functionCall']['args'] ?? [],
                    'provider_call_id' => $part['functionCall']['id'] ?? null,
                    'thought_signature' => $part['thoughtSignature'] ?? null,
                ];
            }
        }

        if ($content === []) {
            throw new AiProviderException(
                'Resposta vazia, bloqueada ou inválida da API Gemini.',
                $this->name(),
                errorType: $response->json('promptFeedback.blockReason') ?? 'invalid_response',
                fallbackAllowed: true,
            );
        }

        $usage = $response->json('usageMetadata', []);
        $cachedTokens = (int) ($usage['cachedContentTokenCount'] ?? 0);
        $hasToolUse = collect($content)->contains('type', 'tool_use');

        return new AiResponse(
            provider: $this->name(),
            model: $request->model,
            content: $content,
            stopReason: $hasToolUse ? 'tool_use' : $response->json('candidates.0.finishReason'),
            usage: [
                'input_tokens' => max(0, (int) ($usage['promptTokenCount'] ?? 0) - $cachedTokens),
                'output_tokens' => (int) ($usage['candidatesTokenCount'] ?? 0),
                'cache_creation_input_tokens' => 0,
                'cache_read_input_tokens' => $cachedTokens,
            ],
            costUsd: 0,
            latencyMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
            requestId: $response->header('x-request-id'),
        );
    }

    private function toGeminiPayload(array $payload): array
    {
        $system = collect($payload['system'] ?? [])->pluck('text')->filter()->join("\n\n");
        $contents = [];
        $toolCallsById = [];

        foreach ($payload['messages'] ?? [] as $message) {
            $role = $message['role'] === 'assistant' ? 'model' : 'user';
            $rawContent = $message['content'] ?? '';

            if (is_string($rawContent)) {
                $contents[] = ['role' => $role, 'parts' => [['text' => $rawContent]]];

                continue;
            }

            $parts = [];
            foreach ($rawContent as $block) {
                if (($block['type'] ?? null) === 'text') {
                    $parts[] = ['text' => $block['text']];
                } elseif (($block['type'] ?? null) === 'tool_use') {
                    $toolCallsById[$block['id']] = [
                        'name' => $block['name'],
                        'provider_call_id' => $block['provider_call_id'] ?? null,
                    ];
                    $functionCall = [
                        'name' => $block['name'],
                        'args' => $block['input'] ?? [],
                    ];
                    if (filled($block['provider_call_id'] ?? null)) {
                        $functionCall['id'] = $block['provider_call_id'];
                    }
                    $part = ['functionCall' => $functionCall];
                    if (filled($block['thought_signature'] ?? null)) {
                        $part['thoughtSignature'] = $block['thought_signature'];
                    }
                    $parts[] = $part;
                } elseif (($block['type'] ?? null) === 'tool_result') {
                    $decoded = is_string($block['content']) ? json_decode($block['content'], true) : $block['content'];
                    $toolCall = $toolCallsById[$block['tool_use_id']] ?? ['name' => 'tool', 'provider_call_id' => null];
                    $functionResponse = [
                        'name' => $toolCall['name'],
                        'response' => is_array($decoded) ? $decoded : ['result' => $block['content']],
                    ];
                    if (filled($toolCall['provider_call_id'])) {
                        $functionResponse['id'] = $toolCall['provider_call_id'];
                    }
                    $parts[] = ['functionResponse' => $functionResponse];
                }
            }
            if ($parts) {
                $contents[] = ['role' => $role, 'parts' => $parts];
            }
        }

        return array_filter([
            'systemInstruction' => $system !== '' ? ['parts' => [['text' => $system]]] : null,
            'contents' => $contents,
            'tools' => ! empty($payload['tools']) ? [[
                'functionDeclarations' => collect($payload['tools'])->map(fn ($tool) => [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['input_schema'] ?? ['type' => 'object', 'properties' => []],
                ])->values()->all(),
            ]] : null,
            'generationConfig' => ['maxOutputTokens' => $payload['max_tokens'] ?? 1024],
        ], static fn ($value) => $value !== null);
    }
}
