<?php

namespace App\Models;

use Carbon\Carbon;
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
        'horarios_funcionamento', 'nome_agente', 'tom_voz', 'instrucoes_extras', 'bot_saudacao', 'bot_ativo',
        'webhook_token',
        // triagem / horário de atendimento
        'modo_bot', 'horario_atendimento', 'mensagem_fora_horario',
    ];

    protected $casts = [
        'configuracoes'        => 'array',
        'horario_atendimento'  => 'array',
        'whatsapp_conectado'   => 'boolean',
        'ativo'                => 'boolean',
        'bot_ativo'            => 'boolean',
        'isento_cobranca'      => 'boolean',
        'trial_ends_at'        => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    /** Dias da semana no índice 0=Dom .. 6=Sáb usado por horario_atendimento. */
    public const DIAS_SEMANA = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    /** Tipos de estabelecimento aceitos (fonte única para validação). */
    public const TIPOS_SERVICO = ['barbeiro', 'quadra', 'estetica', 'clinica', 'studio', 'personalizado'];

    /** Tons de voz aceitos para o bot. */
    public const TONS_VOZ = ['formal', 'semiformal', 'descontraido'];

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
    public function bloqueiosAgenda(): HasMany { return $this->hasMany(BloqueioAgenda::class); }

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

    /**
     * Verifica se o momento informado está dentro do horário de atendimento
     * configurado. Sem horário configurado, considera-se sempre em atendimento.
     */
    public function emHorarioAtendimento(?Carbon $agora = null): bool
    {
        $horarios = $this->horario_atendimento;
        if (empty($horarios) || ! is_array($horarios)) {
            return true;
        }

        $agora = ($agora ?? Carbon::now())->copy()->setTimezone('America/Sao_Paulo');
        $dia   = (int) $agora->format('w'); // 0=Dom .. 6=Sáb

        $config = $horarios[$dia] ?? $horarios[(string) $dia] ?? null;
        if (! is_array($config) || empty($config['ativo'])) {
            return false;
        }

        $abertura   = $config['abertura']   ?? null;
        $fechamento = $config['fechamento'] ?? null;
        if (! $abertura || ! $fechamento) {
            return false;
        }

        $atual = $agora->format('H:i');

        return $atual >= $abertura && $atual < $fechamento;
    }

    /**
     * Texto legível do horário de atendimento (ex.: "Seg–Sex 08:00–18:00, Sáb 08:00–12:00"),
     * agrupando dias consecutivos com a mesma faixa. Retorna string vazia se nada configurado.
     */
    public function horarioAtendimentoTexto(): string
    {
        $horarios = $this->horario_atendimento;
        if (empty($horarios) || ! is_array($horarios)) {
            return '';
        }

        $grupos = [];
        for ($dia = 0; $dia <= 6; $dia++) {
            $config = $horarios[$dia] ?? $horarios[(string) $dia] ?? null;
            if (! is_array($config) || empty($config['ativo']) || empty($config['abertura']) || empty($config['fechamento'])) {
                continue;
            }

            $faixa = "{$config['abertura']}–{$config['fechamento']}";
            $ultimo = end($grupos) ?: null;

            if ($ultimo && $ultimo['faixa'] === $faixa && $ultimo['fim'] === $dia - 1) {
                $grupos[array_key_last($grupos)]['fim'] = $dia;
            } else {
                $grupos[] = ['inicio' => $dia, 'fim' => $dia, 'faixa' => $faixa];
            }
        }

        return collect($grupos)->map(function ($g) {
            $dias = $g['inicio'] === $g['fim']
                ? self::DIAS_SEMANA[$g['inicio']]
                : self::DIAS_SEMANA[$g['inicio']] . '–' . self::DIAS_SEMANA[$g['fim']];

            return "{$dias} {$g['faixa']}";
        })->join(', ');
    }
}
