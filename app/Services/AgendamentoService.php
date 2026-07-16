<?php

namespace App\Services;

use App\Exceptions\HorarioIndisponivelException;
use App\Jobs\SyncGoogleCalendarJob;
use App\Models\Agendamento;
use App\Models\BloqueioAgenda;
use App\Models\OperationalEvent;
use App\Models\Profissional;
use App\Models\Recurso;
use App\Models\Servico;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgendamentoService
{
    /**
     * Criação manual pelo painel (atendente). Suporta tanto `recurso_id` (tenants tipo
     * quadra/personalizado, sem conceito de expediente) quanto `profissional_id` (com
     * expediente). Aplica as mesmas regras de antecedência/buffer usadas pelo bot.
     */
    public function criar(Tenant $tenant, array $dados): Agendamento
    {
        return DB::transaction(function () use ($tenant, $dados) {
            // O tenant da sessão/serviço é a única fonte de verdade.
            $dados['tenant_id'] = $tenant->id;
            $regras = $tenant->regrasAgendamentoConfig();
            $tz = config('app.timezone', 'America/Sao_Paulo');
            $buffer = (int) $regras['buffer_entre_agendamentos_minutos'];

            $lockId = $dados['recurso_id'] ?? $dados['profissional_id'] ?? 0;
            DB::select('SELECT pg_advisory_xact_lock(?)', [$lockId]);

            $inicio = Carbon::parse($dados['inicio'], $tz);
            $fim = Carbon::parse($dados['fim'], $tz);

            $this->validarAntecedencia($inicio, $regras, $tz);

            if (! empty($dados['recurso_id'])) {
                Recurso::where('id', $dados['recurso_id'])->where('tenant_id', $tenant->id)->firstOrFail();
                $this->validarConflitoRecurso((int) $dados['recurso_id'], $inicio, $fim, $buffer);
            } elseif (! empty($dados['profissional_id'])) {
                $profissional = Profissional::where('id', $dados['profissional_id'])
                    ->where('tenant_id', $tenant->id)
                    ->where('ativo', true)
                    ->firstOrFail();

                if (! empty($dados['servico_id'])) {
                    Servico::where('id', $dados['servico_id'])
                        ->where('tenant_id', $tenant->id)
                        ->firstOrFail();
                }
                $this->validarExpediente($profissional, $inicio, $fim, $tz);
                $this->validarConflitoProfissional((int) $dados['profissional_id'], $inicio, $fim, $buffer);
            }

            $agendamento = Agendamento::create($dados);
            OperationalEvent::record($tenant->id, 'appointment_created', [
                'value' => $agendamento->valor_total,
                'metadata' => ['origin' => $agendamento->origem ?? 'manual'],
            ]);
            SyncGoogleCalendarJob::dispatch($agendamento)->onQueue('sync')->afterCommit();

            return $agendamento;
        });
    }

    /**
     * Atualização manual pelo painel (atendente). Mesma lógica de validação de `criar()`,
     * ignorando o próprio agendamento na checagem de conflito.
     */
    public function atualizar(Agendamento $agendamento, array $dados): Agendamento
    {
        return DB::transaction(function () use ($agendamento, $dados) {
            $tenant = $agendamento->tenant;
            $regras = $tenant->regrasAgendamentoConfig();
            $tz = config('app.timezone', 'America/Sao_Paulo');
            $buffer = (int) $regras['buffer_entre_agendamentos_minutos'];

            $recursoId = $dados['recurso_id'] ?? $agendamento->recurso_id;
            $profissionalId = $recursoId ? null : $agendamento->profissional_id;

            $lockId = $recursoId ?? $profissionalId ?? 0;
            DB::select('SELECT pg_advisory_xact_lock(?)', [$lockId]);

            $inicio = Carbon::parse($dados['inicio'], $tz);
            $fim = Carbon::parse($dados['fim'], $tz);

            $this->validarAntecedencia($inicio, $regras, $tz);

            $updateData = [
                'cliente_nome' => $dados['cliente_nome'],
                'cliente_telefone' => $dados['cliente_telefone'],
                'inicio' => $inicio,
                'fim' => $fim,
                'data_hora' => $inicio,
                'duracao_minutos' => (int) $inicio->diffInMinutes($fim),
                'observacoes' => $dados['observacoes'] ?? null,
                'status' => $dados['status'] ?? $agendamento->status,
            ];

            if ($recursoId) {
                $recurso = Recurso::where('id', $recursoId)->where('tenant_id', $tenant->id)->firstOrFail();
                $this->validarConflitoRecurso($recursoId, $inicio, $fim, $buffer, ignorarAgendamentoId: $agendamento->id);
                $updateData['recurso_id'] = $recurso->id;
                $updateData['valor_total'] = $recurso->valor_hora
                    ? $recurso->valor_hora * $inicio->diffInMinutes($fim) / 60
                    : null;
            } elseif ($profissionalId) {
                $profissional = Profissional::where('id', $profissionalId)->where('tenant_id', $tenant->id)->firstOrFail();
                $this->validarExpediente($profissional, $inicio, $fim, $tz);
                $this->validarConflitoProfissional($profissionalId, $inicio, $fim, $buffer, ignorarAgendamentoId: $agendamento->id);
            }

            $agendamento->update($updateData);

            return $agendamento->fresh();
        });
    }

    public function cancelar(Agendamento $agendamento): void
    {
        $agendamento->update(['status' => 'cancelado']);
    }

    private function validarAntecedencia(Carbon $inicio, array $regras, string $tz): void
    {
        $antecedenciaMinima = Carbon::now($tz)->addMinutes($regras['antecedencia_minima_minutos']);
        if ($inicio->lt($antecedenciaMinima)) {
            throw new HorarioIndisponivelException(
                $regras['antecedencia_minima_minutos'] > 0
                    ? "É preciso agendar com pelo menos {$regras['antecedencia_minima_minutos']} minutos de antecedência."
                    : 'Não é possível agendar para datas passadas.'
            );
        }

        $antecedenciaMaxima = Carbon::now($tz)->addDays($regras['antecedencia_maxima_dias']);
        if ($inicio->gt($antecedenciaMaxima)) {
            throw new HorarioIndisponivelException(
                "Só é possível agendar com até {$regras['antecedencia_maxima_dias']} dias de antecedência."
            );
        }
    }

    private function validarExpediente(Profissional $profissional, Carbon $inicio, Carbon $fim, string $tz): void
    {
        $diaSemana = (int) $inicio->format('N');
        if ($diaSemana === 7) {
            $diaSemana = 0;
        }
        $horarioDia = $profissional->horarios()->where('dia_semana', $diaSemana)->first();
        if (! $horarioDia) {
            throw new HorarioIndisponivelException(
                "{$profissional->nome} não atende neste dia. Escolha outro dia disponível."
            );
        }
        $expedienteInicio = Carbon::createFromFormat('Y-m-d H:i:s', $inicio->format('Y-m-d').' '.$horarioDia->hora_inicio, $tz);
        $expedienteFim = Carbon::createFromFormat('Y-m-d H:i:s', $inicio->format('Y-m-d').' '.$horarioDia->hora_fim, $tz);
        if ($inicio->lt($expedienteInicio) || $fim->gt($expedienteFim)) {
            $abertura = substr($horarioDia->hora_inicio, 0, 5);
            $fechamento = substr($horarioDia->hora_fim, 0, 5);
            throw new HorarioIndisponivelException(
                "{$profissional->nome} atende neste dia das {$abertura} às {$fechamento}. Escolha um horário dentro desse período."
            );
        }
    }

    /**
     * Range overlap com buffer: detecta qualquer agendamento existente do mesmo profissional
     * que se sobreponha ao slot [inicio, fim), com uma folga configurável antes/depois.
     */
    private function validarConflitoProfissional(int $profissionalId, Carbon $inicio, Carbon $fim, int $buffer, ?int $ignorarAgendamentoId = null): void
    {
        $query = Agendamento::where('profissional_id', $profissionalId)
            ->whereNotIn('status', ['cancelado'])
            ->where('data_hora', '<', $fim->copy()->addMinutes($buffer))
            ->whereRaw("(data_hora + (duracao_minutos * INTERVAL '1 minute') + (? * INTERVAL '1 minute')) > ?", [$buffer, $inicio]);

        if ($ignorarAgendamentoId) {
            $query->where('id', '!=', $ignorarAgendamentoId);
        }

        if ($query->exists()) {
            throw new HorarioIndisponivelException('Horário não disponível.');
        }

        if (BloqueioAgenda::where('profissional_id', $profissionalId)
            ->where('inicio', '<', $fim)
            ->where('fim', '>', $inicio)
            ->exists()) {
            throw new HorarioIndisponivelException('Este horário está bloqueado na agenda.');
        }
    }

    /**
     * Mesma lógica de `validarConflitoProfissional`, para o caminho legado `recurso_id`
     * (sem conceito de expediente), que já guarda `inicio`/`fim` diretamente.
     */
    private function validarConflitoRecurso(int $recursoId, Carbon $inicio, Carbon $fim, int $buffer, ?int $ignorarAgendamentoId = null): void
    {
        $query = Agendamento::where('recurso_id', $recursoId)
            ->where('status', '!=', 'cancelado')
            ->where('inicio', '<', $fim->copy()->addMinutes($buffer))
            ->whereRaw("(fim + (? * INTERVAL '1 minute')) > ?", [$buffer, $inicio]);

        if ($ignorarAgendamentoId) {
            $query->where('id', '!=', $ignorarAgendamentoId);
        }

        if ($query->exists()) {
            throw new HorarioIndisponivelException('Horário não disponível.');
        }

        if (BloqueioAgenda::where('recurso_id', $recursoId)
            ->where('inicio', '<', $fim)
            ->where('fim', '>', $inicio)
            ->exists()) {
            throw new HorarioIndisponivelException('Este horário está bloqueado na agenda.');
        }
    }

    public function reagendar(Agendamento $agendamento, array $dados): Agendamento
    {
        return DB::transaction(function () use ($agendamento, $dados) {
            if ((int) $agendamento->tenant_id !== (int) ($agendamento->tenant?->id)) {
                throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Agendamento fora do tenant atual.');
            }

            $profissionalId = (int) ($dados['profissional_id'] ?? $agendamento->profissional_id);
            Profissional::where('id', $profissionalId)
                ->where('tenant_id', $agendamento->tenant_id)
                ->where('ativo', true)
                ->firstOrFail();

            if (! empty($dados['servico_id'])) {
                Servico::where('id', $dados['servico_id'])
                    ->where('tenant_id', $agendamento->tenant_id)
                    ->firstOrFail();
            }

            DB::select('SELECT pg_advisory_xact_lock(?)', [$profissionalId]);

            $tz = config('app.timezone', 'America/Sao_Paulo');
            $horario = substr($dados['hora'], 0, 5);
            $inicio = Carbon::createFromFormat('Y-m-d H:i', "{$dados['data']} {$horario}", $tz);
            $duracao = $agendamento->duracao_minutos ?? 30;
            $fim = $inicio->copy()->addMinutes($duracao);

            if ($inicio->isPast()) {
                throw new HorarioIndisponivelException('Não é possível reagendar para datas passadas.');
            }

            $profissional = Profissional::findOrFail($profissionalId);
            $this->validarExpediente($profissional, $inicio, $fim, $tz);
            $buffer = (int) $agendamento->tenant->regrasAgendamentoConfig()['buffer_entre_agendamentos_minutos'];
            $this->validarConflitoProfissional(
                $profissionalId,
                $inicio,
                $fim,
                $buffer,
                ignorarAgendamentoId: $agendamento->id,
            );

            $agendamento->update([
                'profissional_id' => $profissionalId,
                'servico_id' => $dados['servico_id'] ?? $agendamento->servico_id,
                'data_hora' => $inicio,
                'inicio' => $inicio,
                'fim' => $fim,
            ]);

            return $agendamento->fresh();
        });
    }

    public function buscarHorariosDisponiveis(Tenant $tenant, int $dias = 7): array
    {
        $regras = $tenant->regrasAgendamentoConfig();
        $dias = min($dias, $regras['antecedencia_maxima_dias']);

        $profissionais = $tenant->profissionais()->where('ativo', true)->with('horarios')->get();
        $resultado = [];

        $tz = config('app.timezone', 'America/Sao_Paulo');
        $antecedenciaMinima = Carbon::now($tz)->addMinutes($regras['antecedencia_minima_minutos']);

        foreach ($profissionais as $profissional) {
            $resultado[$profissional->id] = [];

            for ($i = 0; $i < $dias; $i++) {
                $data = Carbon::today($tz)->addDays($i);
                $slots = $profissional->slotsDisponiveis($data);
                $disponiveis = collect($slots)
                    ->where('disponivel', true)
                    ->pluck('hora')
                    ->filter(fn ($hora) => Carbon::createFromFormat('Y-m-d H:i', "{$data->format('Y-m-d')} {$hora}", $tz)->gte($antecedenciaMinima))
                    ->values()
                    ->all();

                if (! empty($disponiveis)) {
                    $resultado[$profissional->id][$data->format('Y-m-d')] = $disponiveis;
                }
            }
        }

        return $resultado;
    }

    public function criarAgendamentoV2(Tenant $tenant, array $dados): Agendamento
    {
        // Validar campos obrigatórios antes de entrar na transação
        if (empty($dados['data']) || empty($dados['horario'])) {
            throw new \InvalidArgumentException('Data e horário são obrigatórios.');
        }
        if (empty($dados['profissional_id'])) {
            throw new \InvalidArgumentException('Profissional é obrigatório.');
        }
        if (empty($dados['cliente_nome'])) {
            throw new \InvalidArgumentException('Nome do cliente é obrigatório.');
        }
        if (empty($dados['cliente_telefone'])) {
            throw new \InvalidArgumentException('Telefone do cliente é obrigatório.');
        }

        return DB::transaction(function () use ($tenant, $dados) {
            $regras = $tenant->regrasAgendamentoConfig();

            $profissionalId = (int) $dados['profissional_id'];

            // Tenant isolation: garantir que o profissional pertence ao tenant e está ativo
            $profissional = Profissional::where('id', $profissionalId)
                ->where('tenant_id', $tenant->id)
                ->where('ativo', true)
                ->firstOrFail();

            DB::select('SELECT pg_advisory_xact_lock(?)', [$profissionalId]);

            // Tenant isolation: garantir que o serviço pertence ao tenant
            $servico = null;
            if (! empty($dados['servico_id'])) {
                $servico = Servico::where('id', $dados['servico_id'])
                    ->where('tenant_id', $tenant->id)
                    ->firstOrFail();
            }

            if (! empty($dados['cliente_id'])) {
                \App\Models\Cliente::where('id', $dados['cliente_id'])
                    ->where('tenant_id', $tenant->id)
                    ->firstOrFail();
            }
            $duracao = $servico?->duracao_minutos ?? $dados['duracao_minutos'] ?? 30;

            // Timezone explícito para garantir que "10:00" seja interpretado como horário local
            $tz = config('app.timezone', 'America/Sao_Paulo');
            $horario = substr($dados['horario'], 0, 5); // garante formato HH:MM (descarta segundos se vierem)
            $inicio = Carbon::createFromFormat('Y-m-d H:i', "{$dados['data']} {$horario}", $tz);
            $fim = $inicio->copy()->addMinutes($duracao);

            $this->validarAntecedencia($inicio, $regras, $tz);
            $this->validarExpediente($profissional, $inicio, $fim, $tz);

            $buffer = (int) $regras['buffer_entre_agendamentos_minutos'];
            $this->validarConflitoProfissional($profissionalId, $inicio, $fim, $buffer);

            $agendamento = Agendamento::create([
                'tenant_id' => $tenant->id,
                'cliente_id' => $dados['cliente_id'],
                'cliente_nome' => $dados['cliente_nome'],
                'cliente_telefone' => $dados['cliente_telefone'],
                'profissional_id' => $profissional->id,
                'servico_id' => $servico?->id,
                'data_hora' => $inicio,
                'inicio' => $inicio,
                'fim' => $fim,
                'duracao_minutos' => $duracao,
                'status' => 'agendado',
                'opcao_extra' => $dados['opcao_extra'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
                'origem' => $dados['origem'] ?? 'bot',
            ]);

            OperationalEvent::record($tenant->id, 'appointment_created', [
                'value' => $agendamento->valor_total,
                'metadata' => ['origin' => $agendamento->origem ?? 'bot'],
            ]);
            SyncGoogleCalendarJob::dispatch($agendamento)->onQueue('sync')->afterCommit();

            Log::channel('db')->info('AGENDAMENTO_INSERT', [
                'id' => $agendamento->id,
                'tenant_id' => $tenant->id,
                'origin' => $agendamento->origem,
                'profissional_id' => $agendamento->profissional_id,
                'servico_id' => $agendamento->servico_id,
                'data_hora_brt' => $inicio->toDateTimeString(),
                'data_hora_utc' => $inicio->utc()->toDateTimeString(),
                'duracao_minutos' => $duracao,
                'status' => 'agendado',
                'origem' => $agendamento->origem,
            ]);

            return $agendamento;
        });
    }
}
