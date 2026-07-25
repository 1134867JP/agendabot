<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenUsage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'provider', 'model',
        'input_tokens', 'output_tokens',
        'cache_creation_input_tokens', 'cache_read_input_tokens',
        'cost_usd', 'latency_ms', 'request_id', 'created_at',
    ];

    protected $casts = [
        'cost_usd' => 'decimal:8',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Preços Haiku 4.5 em USD por token
    const PRECO_INPUT = 0.000001;    // $1.00 / MTok

    const PRECO_OUTPUT = 0.000005;    // $5.00 / MTok

    const PRECO_CACHE_WRITE = 0.00000125;  // $1.25 / MTok

    const PRECO_CACHE_READ = 0.0000001;   // $0.10 / MTok

    public static function calcularCusto(int $input, int $output, int $cacheWrite, int $cacheRead): float
    {
        return ($input * self::PRECO_INPUT)
             + ($output * self::PRECO_OUTPUT)
             + ($cacheWrite * self::PRECO_CACHE_WRITE)
             + ($cacheRead * self::PRECO_CACHE_READ);
    }

    public static function calcularCustoModelo(string $provider, string $model, array $usage): float
    {
        $pricing = self::precosModelo($provider, $model);

        return round(
            ((int) ($usage['input_tokens'] ?? 0) / 1_000_000) * $pricing['input']
            + ((int) ($usage['output_tokens'] ?? 0) / 1_000_000) * $pricing['output']
            + ((int) ($usage['cache_creation_input_tokens'] ?? 0) / 1_000_000) * $pricing['cache_write']
            + ((int) ($usage['cache_read_input_tokens'] ?? 0) / 1_000_000) * $pricing['cache_read'],
            8,
        );
    }

    public static function precosModelo(string $provider, string $model): array
    {
        $providerPricing = config("ai.pricing.{$provider}", []);

        return ($providerPricing[$model] ?? null)
            ?? ($providerPricing['default'] ?? null)
            ?? ['input' => 0, 'output' => 0, 'cache_write' => 0, 'cache_read' => 0];
    }
}
