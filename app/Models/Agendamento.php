<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agendamento extends Model
{
    protected $fillable = [
        'tenant_id', 'recurso_id', 'cliente_nome', 'cliente_telefone',
        'inicio', 'fim', 'status', 'origem', 'observacoes', 'valor_total',
        'lembrete_enviado',
    ];

    protected $casts = [
        'inicio'           => 'datetime',
        'fim'              => 'datetime',
        'valor_total'      => 'decimal:2',
        'lembrete_enviado' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(Recurso::class);
    }
}
