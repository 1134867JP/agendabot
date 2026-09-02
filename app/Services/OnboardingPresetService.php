<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class OnboardingPresetService
{
    /**
     * Aplica uma configuração inicial segura e idempotente. O objetivo é deixar
     * o tenant testável sem exigir que o usuário entenda todas as telas do painel.
     */
    public function aplicar(Tenant $tenant, array $dados): void
    {
        DB::transaction(function () use ($tenant, $dados) {
            $dias = $this->diasDoPreset($dados['dias_atendimento']);
            $horariosSemana = $this->horariosSemana(
                $dias,
                $dados['hora_abertura'],
                $dados['hora_fechamento'],
            );
            $regras = $this->regrasDoPreset($dados['perfil_regras']);

            $configuracoes = array_replace_recursive($tenant->configuracoes ?? [], [
                'onboarding_express' => [
                    'concluido' => true,
                    'perfil_regras' => $dados['perfil_regras'],
                ],
                'horarios_funcionamento_semana' => $horariosSemana,
                'regras_agendamento' => $regras,
                'agenda' => $this->agendaInicial($tenant),
            ]);

            $tenant->update([
                'ramo_negocio' => $this->rotuloTipo($tenant),
                'nome_agente' => "Assistente da {$tenant->nome}",
                'tom_voz' => 'semiformal',
                'bot_saudacao' => "Olá! Bem-vindo à {$tenant->nome}. Como posso ajudar?",
                'bot_ativo' => true,
                'horarios_funcionamento' => $this->resumoHorario(
                    $dados['dias_atendimento'],
                    $dados['hora_abertura'],
                    $dados['hora_fechamento'],
                ),
                'configuracoes' => $configuracoes,
            ]);

            if ($tenant->tipo_servico === 'quadra') {
                $this->criarRecurso($tenant, $dados, $dias);

                return;
            }

            $this->criarProfissionalEServico($tenant, $dados, $dias);

            // "Personalizado" ainda oferece os dois catálogos no produto atual.
            // Criar também um recurso evita deixar o checklist de ativação travado.
            if ($tenant->tipo_servico === 'personalizado') {
                $this->criarRecurso($tenant, $dados, $dias);
            }
        });
    }

    public function defaults(Tenant $tenant, string $nomeUsuario): array
    {
        $preset = match ($tenant->tipo_servico) {
            'barbeiro' => ['item' => $nomeUsuario, 'servico' => 'Corte', 'duracao' => 30, 'valor' => 40],
            'quadra' => ['item' => 'Quadra principal', 'servico' => 'Reserva', 'duracao' => 60, 'valor' => 120],
            'estetica' => ['item' => $nomeUsuario, 'servico' => 'Atendimento', 'duracao' => 60, 'valor' => 80],
            'clinica' => ['item' => $nomeUsuario, 'servico' => 'Consulta', 'duracao' => 50, 'valor' => 120],
            'studio' => ['item' => $nomeUsuario, 'servico' => 'Sessão', 'duracao' => 60, 'valor' => 100],
            default => ['item' => $nomeUsuario, 'servico' => 'Atendimento', 'duracao' => 60, 'valor' => 80],
        };

        return [
            'nome_item' => $preset['item'],
            'nome_servico' => $preset['servico'],
            'duracao_minutos' => $preset['duracao'],
            'valor' => $preset['valor'],
            'dias_atendimento' => 'segunda_sabado',
            'hora_abertura' => '09:00',
            'hora_fechamento' => '18:00',
            'perfil_regras' => 'equilibrado',
        ];
    }

    private function criarProfissionalEServico(Tenant $tenant, array $dados, array $dias): void
    {
        $profissional = $tenant->profissionais()->updateOrCreate(
            ['nome' => $dados['nome_item']],
            ['ativo' => true],
        );

        $servico = $tenant->servicos()->updateOrCreate(
            ['nome' => $dados['nome_servico']],
            [
                'valor_min' => $dados['valor'],
                'valor_max' => $dados['valor'],
                'duracao_minutos' => $dados['duracao_minutos'],
                'ativo' => true,
                'requer_profissional' => true,
                'requer_recurso' => false,
            ],
        );

        $profissional->servicos()->syncWithoutDetaching([$servico->id]);

        foreach ($dias as $dia) {
            $profissional->horarios()->updateOrCreate(
                ['dia_semana' => $dia],
                [
                    'hora_inicio' => $dados['hora_abertura'],
                    'hora_fim' => $dados['hora_fechamento'],
                    'duracao_slot' => $dados['duracao_minutos'],
                ],
            );
        }
    }

    private function criarRecurso(Tenant $tenant, array $dados, array $dias): void
    {
        $recurso = $tenant->recursos()->updateOrCreate(
            ['nome' => $dados['nome_item']],
            [
                'valor_hora' => $dados['valor'],
                'duracao_padrao_minutos' => $dados['duracao_minutos'],
                'ativo' => true,
            ],
        );

        foreach ($dias as $dia) {
            $recurso->horariosFuncionamento()->updateOrCreate(
                ['dia_semana' => $dia],
                [
                    'abertura' => $dados['hora_abertura'],
                    'fechamento' => $dados['hora_fechamento'],
                ],
            );
        }
    }

    private function agendaInicial(Tenant $tenant): array
    {
        return match ($tenant->tipo_servico) {
            'quadra' => ['modo' => 'recurso', 'rotulo_recurso' => 'Quadra', 'rotulo_recursos' => 'Quadras'],
            'clinica' => ['modo' => 'combinada', 'rotulo_recurso' => 'Sala', 'rotulo_recursos' => 'Salas'],
            'personalizado' => ['modo' => 'combinada'],
            default => ['modo' => 'profissional'],
        };
    }

    private function diasDoPreset(string $preset): array
    {
        return match ($preset) {
            'segunda_sexta' => [1, 2, 3, 4, 5],
            'todos' => [0, 1, 2, 3, 4, 5, 6],
            default => [1, 2, 3, 4, 5, 6],
        };
    }

    private function horariosSemana(array $dias, string $abertura, string $fechamento): array
    {
        return collect(range(0, 6))->map(fn (int $dia) => [
            'ativo' => in_array($dia, $dias, true),
            'periodos' => in_array($dia, $dias, true)
                ? [['abertura' => $abertura, 'fechamento' => $fechamento]]
                : [],
        ])->all();
    }

    private function regrasDoPreset(string $perfil): array
    {
        return match ($perfil) {
            'flexivel' => [
                'antecedencia_minima_minutos' => 0,
                'antecedencia_maxima_dias' => 60,
                'buffer_entre_agendamentos_minutos' => 0,
                'permite_cliente_remarcar' => true,
                'permite_cliente_cancelar' => true,
                'politica_cancelamento' => null,
            ],
            'protegido' => [
                'antecedencia_minima_minutos' => 120,
                'antecedencia_maxima_dias' => 30,
                'buffer_entre_agendamentos_minutos' => 15,
                'permite_cliente_remarcar' => true,
                'permite_cliente_cancelar' => true,
                'politica_cancelamento' => 'Cancelamentos e remarcações devem ser feitos com pelo menos 2 horas de antecedência.',
            ],
            default => [
                'antecedencia_minima_minutos' => 30,
                'antecedencia_maxima_dias' => 30,
                'buffer_entre_agendamentos_minutos' => 0,
                'permite_cliente_remarcar' => true,
                'permite_cliente_cancelar' => true,
                'politica_cancelamento' => null,
            ],
        };
    }

    private function resumoHorario(string $dias, string $abertura, string $fechamento): string
    {
        $rotulo = match ($dias) {
            'segunda_sexta' => 'Seg–Sex',
            'todos' => 'Todos os dias',
            default => 'Seg–Sáb',
        };

        return "{$rotulo} {$abertura}–{$fechamento}";
    }

    private function rotuloTipo(Tenant $tenant): string
    {
        return $tenant->tipo_servico_personalizado ?: match ($tenant->tipo_servico) {
            'barbeiro' => 'Barbearia',
            'quadra' => 'Quadra esportiva',
            'estetica' => 'Estética',
            'clinica' => 'Clínica',
            'studio' => 'Estúdio',
            default => 'Serviços',
        };
    }
}
