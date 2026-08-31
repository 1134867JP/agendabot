<?php

$csv = static fn (?string $value): array => array_values(array_filter(array_map(
    'trim',
    explode(',', (string) $value),
)));
$nullableInt = static fn (mixed $value): ?int => $value === null || trim((string) $value) === ''
    ? null
    : (int) $value;
$nullableFloat = static fn (mixed $value): ?float => $value === null || trim((string) $value) === ''
    ? null
    : (float) $value;

$cloudflareAccountId = env('CLOUDFLARE_ACCOUNT_ID');
$cloudflareBaseUrl = env(
    'CLOUDFLARE_BASE_URL',
    filled($cloudflareAccountId)
        ? "https://api.cloudflare.com/client/v4/accounts/{$cloudflareAccountId}/ai/v1"
        : null,
);

return [
    'default_provider' => env('AI_PROVIDER', 'groq_qwen'),
    'fallback_providers' => $csv(env('AI_FALLBACK_PROVIDERS', 'groq_gpt_oss,cloudflare,gemini,openrouter')),
    'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 30),
    // 400 entra aqui porque cada provider tem sua própria serialização/schema: uma
    // peculiaridade que faz UM provider rejeitar a requisição (ex.: histórico de
    // function-calling multi-turno específico do Gemini) não implica que os demais
    // (com autenticação e formato de payload independentes) também rejeitem.
    'fallback_statuses' => [400, 404, 408, 409, 425, 429, 500, 502, 503, 504, 529],

    'limits' => [
        'monthly_tokens' => $nullableInt(env('AI_MONTHLY_TOKEN_LIMIT')),
        'monthly_cost_usd' => $nullableFloat(env('AI_MONTHLY_COST_LIMIT_USD')),
    ],

    'providers' => [
        'claude' => [
            'key' => env('CLAUDE_API_KEY'),
            'model' => env('CLAUDE_MODEL', 'claude-haiku-4-5-20251001'),
            'base_url' => env('CLAUDE_BASE_URL', 'https://api.anthropic.com'),
        ],
        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
        // Duas rotas Groq distintas permitem consumir primeiro a cota maior do Qwen
        // e, ao receber erro/rate limit, cair para GPT-OSS usando a mesma API key.
        'groq_qwen' => [
            'key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_QWEN_MODEL', 'qwen/qwen3.8-27b'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        ],
        'groq_gpt_oss' => [
            'key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_GPT_OSS_MODEL', 'openai/gpt-oss-20b'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        ],
        // Mantido para compatibilidade com tenants antigos que ainda tenham
        // provider=groq ou models.groq persistido em configuracoes.ai.
        'groq' => [
            'key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        ],
        'cloudflare' => [
            'key' => env('CLOUDFLARE_API_TOKEN'),
            'model' => env('CLOUDFLARE_MODEL', '@cf/openai/gpt-oss-20b'),
            'base_url' => $cloudflareBaseUrl,
        ],
        'openrouter' => [
            'key' => env('OPENROUTER_API_KEY'),
            'model' => env('OPENROUTER_MODEL', 'openrouter/free'),
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
        'groq_qwen' => [
            'qwen/qwen3.8-27b' => ['input' => 0.80, 'output' => 4.00, 'cache_write' => 0, 'cache_read' => 0],
            'default' => ['input' => 0, 'output' => 0, 'cache_write' => 0, 'cache_read' => 0],
        ],
        'groq_gpt_oss' => [
            'openai/gpt-oss-20b' => ['input' => 0.075, 'output' => 0.30, 'cache_write' => 0, 'cache_read' => 0.037],
            'default' => ['input' => 0, 'output' => 0, 'cache_write' => 0, 'cache_read' => 0],
        ],
        'groq' => [
            'openai/gpt-oss-120b' => ['input' => 0.15, 'output' => 0.60, 'cache_write' => 0, 'cache_read' => 0.075],
            'openai/gpt-oss-20b' => ['input' => 0.075, 'output' => 0.30, 'cache_write' => 0, 'cache_read' => 0.037],
            'default' => ['input' => 0, 'output' => 0, 'cache_write' => 0, 'cache_read' => 0],
        ],
        'cloudflare' => [
            'default' => ['input' => 0, 'output' => 0, 'cache_write' => 0, 'cache_read' => 0],
        ],
        'openrouter' => [
            'anthropic/claude-haiku-4.5' => ['input' => 1, 'output' => 5, 'cache_write' => 1.25, 'cache_read' => 0.10],
            'default' => ['input' => 0, 'output' => 0, 'cache_write' => 0, 'cache_read' => 0],
        ],
    ],
];
