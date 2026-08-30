<?php

namespace App\Services\AI;

use App\Models\OperationalEvent;
use App\Models\Tenant;
use App\Models\TokenUsage;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\DTO\AiRequest;
use App\Services\AI\DTO\AiResponse;
use App\Services\AI\Exceptions\AiLimitExceededException;
use App\Services\AI\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Log;

class AiOrchestrator
{
    /** @var array<string, AiProviderInterface> */
    private array $providers;

    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->name()] = $provider;
        }
    }

    public function processar(Tenant $tenant, array $payload): AiResponse
    {
        $settings = $tenant->aiConfig();
        $this->assertWithinTenantLimits($tenant, $settings);

        $providerNames = array_values(array_unique(array_filter([
            $settings['provider'],
            ...$settings['fallback_providers'],
        ])));
        $lastException = null;

        foreach ($providerNames as $index => $providerName) {
            $provider = $this->providers[$providerName] ?? null;

            if (! $provider || ! $provider->configured()) {
                $lastException = new AiProviderException(
                    "Provider {$providerName} não está configurado.",
                    $providerName,
                    fallbackAllowed: true,
                );

                continue;
            }

            $model = $settings['models'][$providerName] ?? config("ai.providers.{$providerName}.model");
            // Contas novas do Gemini não aceitam mais o modelo antigo. Mantemos esta
            // migração em runtime para também cobrir valores já salvos no .env ou nas
            // configurações dos tenants durante a atualização do deploy.
            if ($providerName === 'gemini' && $model === 'gemini-2.5-flash') {
                $model = 'gemini-3.6-flash';
            }

            try {
                $response = $provider->chat(new AiRequest($payload, $model));
                $costUsd = TokenUsage::calcularCustoModelo($providerName, $model, $response->usage);

                TokenUsage::create([
                    'tenant_id' => $tenant->id,
                    'provider' => $providerName,
                    'model' => $model,
                    ...$response->usage,
                    'cost_usd' => $costUsd,
                    'latency_ms' => $response->latencyMs,
                    'request_id' => $response->requestId,
                ]);

                Log::channel('jobs')->info('AI_REQUEST_SUCCEEDED', [
                    'tenant_id' => $tenant->id,
                    'provider' => $providerName,
                    'model' => $model,
                    'fallback_index' => $index,
                    'latency_ms' => $response->latencyMs,
                    'cost_usd' => $costUsd,
                ]);

                return new AiResponse(
                    provider: $response->provider,
                    model: $response->model,
                    content: $response->content,
                    stopReason: $response->stopReason,
                    usage: $response->usage,
                    costUsd: $costUsd,
                    latencyMs: $response->latencyMs,
                    requestId: $response->requestId,
                );
            } catch (AiProviderException $e) {
                $lastException = $e;
                $hasNextProvider = isset($providerNames[$index + 1]);

                OperationalEvent::record($tenant->id, 'integration_failure', [
                    'provider' => $providerName,
                    'metadata' => [
                        'message' => $e->getMessage(),
                        'status' => $e->status,
                        'error_type' => $e->errorType,
                        'fallback' => $e->fallbackAllowed && $hasNextProvider,
                    ],
                ]);

                Log::channel('jobs')->warning('AI_PROVIDER_FAILED', [
                    'tenant_id' => $tenant->id,
                    'provider' => $providerName,
                    'status' => $e->status,
                    'error_type' => $e->errorType,
                    'fallback_allowed' => $e->fallbackAllowed,
                    'has_next_provider' => $hasNextProvider,
                ]);

                if (! $e->fallbackAllowed) {
                    throw $e;
                }
            }
        }

        throw $lastException ?? new AiProviderException('Nenhum provider de IA disponível.', 'none');
    }

    private function assertWithinTenantLimits(Tenant $tenant, array $settings): void
    {
        $usage = TokenUsage::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('
                COALESCE(SUM(input_tokens + output_tokens + cache_creation_input_tokens + cache_read_input_tokens), 0) AS tokens,
                COALESCE(SUM(cost_usd), 0) AS cost
            ')
            ->first();

        if ($settings['monthly_token_limit'] !== null && (int) $usage->tokens >= $settings['monthly_token_limit']) {
            throw new AiLimitExceededException('Limite mensal de tokens de IA atingido para este tenant.');
        }

        if ($settings['monthly_cost_limit_usd'] !== null && (float) $usage->cost >= $settings['monthly_cost_limit_usd']) {
            throw new AiLimitExceededException('Limite mensal de custo de IA atingido para este tenant.');
        }
    }
}
