<?php

namespace App\Support;

use App\Models\TokenUsage;

class AiStatus
{
    private const LABELS = [
        'claude' => 'Claude',
        'gemini' => 'Gemini',
        'groq' => 'Groq',
        'openrouter' => 'OpenRouter',
    ];

    public static function resumo(): array
    {
        $providerPadrao = (string) config('ai.default_provider', 'claude');
        $fallbacks = array_values(config('ai.fallback_providers', []));
        $ultimaChamada = TokenUsage::query()
            ->latest()
            ->first(['provider', 'model', 'created_at']);

        return [
            'padrao' => self::provider($providerPadrao),
            'fallbacks' => array_map(self::provider(...), $fallbacks),
            'ultima_chamada' => $ultimaChamada ? [
                'provider' => $ultimaChamada->provider,
                'label' => self::label($ultimaChamada->provider),
                'model' => $ultimaChamada->model,
                'em' => $ultimaChamada->created_at->toIso8601String(),
            ] : null,
        ];
    }

    private static function provider(string $provider): array
    {
        return [
            'provider' => $provider,
            'label' => self::label($provider),
            'model' => (string) config("ai.providers.{$provider}.model", ''),
            'configurado' => filled(config("ai.providers.{$provider}.key")),
        ];
    }

    private static function label(string $provider): string
    {
        return self::LABELS[$provider] ?? ucfirst($provider);
    }
}
