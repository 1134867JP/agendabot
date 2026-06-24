<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenUsage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'model',
        'input_tokens', 'output_tokens',
        'cache_creation_input_tokens', 'cache_read_input_tokens',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Preços Haiku 4.5 em USD por token
    const PRECO_INPUT        = 0.000001;    // $1.00 / MTok
    const PRECO_OUTPUT       = 0.000005;    // $5.00 / MTok
    const PRECO_CACHE_WRITE  = 0.00000125;  // $1.25 / MTok
    const PRECO_CACHE_READ   = 0.0000001;   // $0.10 / MTok

    public static function calcularCusto(int $input, int $output, int $cacheWrite, int $cacheRead): float
    {
        return ($input  * self::PRECO_INPUT)
             + ($output * self::PRECO_OUTPUT)
             + ($cacheWrite * self::PRECO_CACHE_WRITE)
             + ($cacheRead  * self::PRECO_CACHE_READ);
    }
}
