<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\DTO\AiRequest;
use App\Services\AI\DTO\AiResponse;
use App\Services\AI\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

abstract class OpenAiCompatibleProvider implements AiProviderInterface
{
    abstract protected function configKey(): string;

    public function configured(): bool
    {
        return filled(config("ai.providers.{$this->configKey()}.key"));
    }

    public function chat(AiRequest $request): AiResponse
    {
        $startedAt = hrtime(true);
        $config = config("ai.providers.{$this->configKey()}");
        $payload = $this->toOpenAiPayload($request->payload, $request->model);

        try {
            $http = Http::timeout((int) config('ai.timeout_seconds', 30))
                ->withToken($config['key'])
                ->acceptJson();

            if ($this->name() === 'openrouter') {
                $http = $http->withHeaders(array_filter([
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => config('app.name'),
                ]));
            }

            $response = $http->post(rtrim((string) $config['base_url'], '/').'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw new AiProviderException($e->getMessage(), $this->name(), fallbackAllowed: true);
        }

        if (! $response->successful()) {
            throw new AiProviderException(
                (string) ($response->json('error.message') ?: "Falha na API {$this->name()}."),
                $this->name(),
                $response->status(),
                $response->json('error.type') ?? $response->json('error.code'),
                in_array($response->status(), config('ai.fallback_statuses', []), true),
            );
        }

        $message = $response->json('choices.0.message', []);
        $content = [];
        if (filled($message['content'] ?? null)) {
            $content[] = ['type' => 'text', 'text' => $message['content']];
        }
        foreach ($message['tool_calls'] ?? [] as $toolCall) {
            $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
            $content[] = [
                'type' => 'tool_use',
                'id' => $toolCall['id'],
                'name' => $toolCall['function']['name'],
                'input' => is_array($arguments) ? $arguments : [],
            ];
        }

        if ($content === []) {
            throw new AiProviderException(
                "Resposta vazia ou inválida de {$this->name()}.",
                $this->name(),
                errorType: 'invalid_response',
                fallbackAllowed: true,
            );
        }

        $usage = $response->json('usage', []);
        $cachedTokens = (int) data_get($usage, 'prompt_tokens_details.cached_tokens', 0);

        return new AiResponse(
            provider: $this->name(),
            model: $request->model,
            content: $content,
            stopReason: ($response->json('choices.0.finish_reason') === 'tool_calls') ? 'tool_use' : $response->json('choices.0.finish_reason'),
            usage: [
                'input_tokens' => max(0, (int) ($usage['prompt_tokens'] ?? 0) - $cachedTokens),
                'output_tokens' => (int) ($usage['completion_tokens'] ?? 0),
                'cache_creation_input_tokens' => 0,
                'cache_read_input_tokens' => $cachedTokens,
            ],
            costUsd: 0,
            latencyMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
            requestId: $response->header('x-request-id'),
        );
    }

    private function toOpenAiPayload(array $payload, string $model): array
    {
        $messages = [];
        $system = collect($payload['system'] ?? [])
            ->pluck('text')
            ->filter()
            ->join("\n\n");

        if ($system !== '') {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        foreach ($payload['messages'] ?? [] as $message) {
            if (is_string($message['content'] ?? null)) {
                $messages[] = $message;

                continue;
            }

            $blocks = $message['content'] ?? [];
            if ($message['role'] === 'assistant') {
                $openAiMessage = ['role' => 'assistant', 'content' => collect($blocks)->where('type', 'text')->pluck('text')->join("\n") ?: null];
                $toolCalls = collect($blocks)->where('type', 'tool_use')->map(fn ($block) => [
                    'id' => $block['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $block['name'],
                        'arguments' => json_encode($block['input'] ?? [], JSON_UNESCAPED_UNICODE),
                    ],
                ])->values()->all();
                if ($toolCalls) {
                    $openAiMessage['tool_calls'] = $toolCalls;
                }
                $messages[] = $openAiMessage;

                continue;
            }

            foreach ($blocks as $block) {
                if (($block['type'] ?? null) === 'tool_result') {
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $block['tool_use_id'],
                        'content' => is_string($block['content']) ? $block['content'] : json_encode($block['content']),
                    ];
                }
            }
        }

        return array_filter([
            'model' => $model,
            'max_tokens' => $payload['max_tokens'] ?? 1024,
            'messages' => $messages,
            'tools' => collect($payload['tools'] ?? [])->map(fn ($tool) => [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['input_schema'] ?? ['type' => 'object', 'properties' => []],
                ],
            ])->values()->all(),
        ], static fn ($value) => $value !== []);
    }
}
