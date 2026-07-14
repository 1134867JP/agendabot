<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalEvent extends Model
{
    protected $fillable = ['tenant_id', 'type', 'provider', 'duration_ms', 'value', 'metadata'];

    protected $casts = ['metadata' => 'array', 'value' => 'decimal:2'];

    public static function record(?int $tenantId, string $type, array $attributes = []): void
    {
        static::create(array_merge($attributes, ['tenant_id' => $tenantId, 'type' => $type]));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
