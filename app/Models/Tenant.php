<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Tenant extends Model
{
    protected $fillable = [
        'nome', 'slug', 'tipo_servico', 'tipo_servico_personalizado',
        'telefone_whatsapp', 'evolution_instance', 'whatsapp_conectado',
        'configuracoes', 'ativo',
        'subscription_status', 'trial_ends_at', 'subscription_ends_at',
        'asaas_customer_id', 'asaas_subscription_id', 'plano', 'taxa_agendamento_bot', 'isento_cobranca',
        // v2
        'ramo_negocio', 'descricao_negocio', 'cidade', 'endereco',
        'horarios_funcionamento', 'nome_agente', 'tom_voz', 'instrucoes_extras', 'bot_ativo',
        'webhook_token',
    ];

    protected $casts = [
        'configuracoes'        => 'array',
        'whatsapp_conectado'   => 'boolean',
        'ativo'                => 'boolean',
        'bot_ativo'            => 'boolean',
        'isento_cobranca'      => 'boolean',
        'trial_ends_at'        => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    public function recursos(): HasMany { return $this->hasMany(Recurso::class); }
    public function agendamentos(): HasMany { return $this->hasMany(Agendamento::class); }
    public function conversas(): HasMany { return $this->hasMany(Conversa::class); }
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot('papel')->withTimestamps();
    }
    public function cobrancasBot(): HasMany { return $this->hasMany(CobrancaBot::class); }
    // v2
    public function profissionais(): HasMany { return $this->hasMany(Profissional::class); }
    public function servicos(): HasMany { return $this->hasMany(Servico::class); }
    public function opcoes_extras(): HasMany { return $this->hasMany(OpcaoExtra::class); }
    public function clientes(): HasMany { return $this->hasMany(Cliente::class); }

    /**
     * Regras de handoff automático bot→humano, com defaults equivalentes ao comportamento
     * atual (sem nenhuma palavra-chave configurada = nenhum handoff automático por triagem).
     */
    public function triagemConfig(): array
    {
        return array_merge([
            'palavras_chave_humano'        => [],
            'max_tentativas_sem_entender'  => 2,
            'transferir_fora_do_horario'   => false,
            'mensagem_transferencia'       => null,
        ], $this->configuracoes['triagem'] ?? []);
    }

    /**
     * Regras gerais de agendamento (antecedência, buffer, cancelamento), com defaults
     * permissivos equivalentes ao comportamento atual.
     */
    public function regrasAgendamentoConfig(): array
    {
        return array_merge([
            'antecedencia_minima_minutos'       => 30,
            'antecedencia_maxima_dias'          => 60,
            'buffer_entre_agendamentos_minutos' => 0,
            'permite_cliente_remarcar'          => true,
            'permite_cliente_cancelar'          => true,
            'politica_cancelamento'             => null,
        ], $this->configuracoes['regras_agendamento'] ?? []);
    }
}
