<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrancaBot extends Model
{
    protected $table = 'cobrancas_bot';

    protected $fillable = [
        'tenant_id',
        'periodo',
        'quantidade_agendamentos',
        'valor_total',
        'asaas_charge_id',
        'status',
    ];

    protected $casts = [
        'valor_total'             => 'decimal:2',
        'quantidade_agendamentos' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
