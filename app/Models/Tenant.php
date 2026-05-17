<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'nome', 'slug', 'tipo_servico', 'tipo_servico_personalizado',
        'telefone_whatsapp', 'evolution_instance', 'whatsapp_conectado',
        'configuracoes', 'ativo',
        'subscription_status', 'trial_ends_at', 'subscription_ends_at',
        'asaas_customer_id', 'asaas_subscription_id', 'plano',
    ];

    protected $casts = [
        'configuracoes'       => 'array',
        'whatsapp_conectado'  => 'boolean',
        'ativo'               => 'boolean',
        'trial_ends_at'       => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    public function recursos(): HasMany
    {
        return $this->hasMany(Recurso::class);
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }

    public function conversas(): HasMany
    {
        return $this->hasMany(Conversa::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot('papel')
            ->withTimestamps();
    }
}
