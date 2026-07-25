<?php

$csv = static fn (?string $value): array => array_values(array_filter(array_map(
    'trim',
    explode(',', (string) $value),
)));

return [
    'default_provider' => env('AI_PROVIDER', 'claude'),
    'fallback_providers' => $csv(env('AI_FALLBACK_PROVIDERS', '')),
    'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 30),
    'fallback_statuses' => [408, 409, 425, 429, 500, 502, 503, 504, 529],

    'limits' => [
        'monthly_tokens' => env('AI_MONTHLY_TOKEN_LIMIT') !== null
            ? (int) env('AI_MONTHLY_TOKEN_LIMIT')
            : null,
        'monthly_cost_usd' => env('AI_MONTHLY_COST_LIMIT_USD') !== null
            ? (float) env('AI_MONTHLY_COST_LIMIT_USD')
            : null,
    ],

    'providers' => [
        'claude' => [
            'key' => env('CLAUDE_API_KEY'),
            'model' => env('CLAUDE_MODEL', 'claude-haiku-4-5-20251001'),
            'base_url' => env('CLAUDE_BASE_URL', 'https://api.anthropic.com'),
        ],
        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
        'groq' => [
            'key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        ],
        'openrouter' => [
            'key' => env('OPENROUTER_API_KEY'),
            'model' => env('OPENROUTER_MODEL', 'anthropic/claude-haiku-4.5'),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        ],
    ],

    /*
     * USD por 1 milhão de tokens. Revise estes valores quando trocar de modelo:
     * os provedores alteram preços independentemente do deploy do Agendou.
     */
    'pricing' => [
        'claude' => [
            'claude-haiku-4-5-20251001' => ['input' => 1, 'output' => 5, 'cache_write' => 1.25, 'cache_read' => 0.10],
            'default' => ['input' => 1, 'output' => 5, 'cache_write' => 1.25, 'cache_read' => 0.10],
        ],
        'gemini' => [
            'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50, 'cache_write' => 0, 'cache_read' => 0.03],
            'default' => ['input' => 0, 'output' => 0, 'cache_write' => 0, 'cache_read' => 0],
        ],
        'groq' => [
            'openai/gpt-oss-120b' => ['input' => 0.15, 'output' => 0.60, 'cache_write' => 0, 'cache_read' => 0.075],
            'default' => ['input' => 0, 'output' => 0, 'cache_write' => 0, 'cache_read' => 0],
        ],
        'openrouter' => [
            'anthropic/claude-haiku-4.5' => ['input' => 1, 'output' => 5, 'cache_write' => 1.25, 'cache_read' => 0.10],
            'default' => ['input' => 0, 'output' => 0, 'cache_write' => 0, 'cache_read' => 0],
        ],
    ],
];
