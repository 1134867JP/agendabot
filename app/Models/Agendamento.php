<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agendamento extends Model
{
    protected $fillable = [
        'tenant_id', 'recurso_id', 'cliente_nome', 'cliente_telefone',
        'inicio', 'fim', 'status', 'origem', 'observacoes', 'valor_total', 'lembrete_enviado',
        // v2
        'cliente_id', 'profissional_id', 'servico_id', 'duracao_minutos', 'opcao_extra', 'data_hora',
        'deposit_status', 'deposit_amount', 'deposit_payment_id', 'deposit_payment_url',
        'calendar_event_id', 'confirmation_sent_at', 'confirmed_by_client_at', 'no_show',
    ];

    protected $casts = [
        'inicio' => 'datetime',
        'fim' => 'datetime',
        'data_hora' => 'datetime',
        'valor_total' => 'decimal:2',
        'lembrete_enviado' => 'boolean',
        'deposit_amount' => 'decimal:2',
        'confirmation_sent_at' => 'datetime',
        'confirmed_by_client_at' => 'datetime',
        'no_show' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(Recurso::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Profissional::class);
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }
}
