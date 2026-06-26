<?php

namespace App\Services;

use App\Exceptions\HorarioIndisponivelException;
use App\Models\Agendamento;
use App\Models\Profissional;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AgendamentoService
{
    public function criar(array $dados): Agendamento
    {
        return DB::transaction(function () use ($dados) {
            $lockId = $dados['recurso_id'] ?? $dados['profissional_id'] ?? 0;
            DB::select('SELECT pg_advisory_xact_lock(?)', [$lockId]);

            if (!empty($dados['recurso_id'])) {
                $conflito = Agendamento::where('recurso_id', $dados['recurso_id'])
                    ->where('status', '!=', 'cancelado')
                    ->where('inicio', '<', $dados['fim'])
                    ->where('fim', '>', $dados['inicio'])
                    ->exists();
            } elseif (!empty($dados['profissional_id'])) {
                $conflito = Agendamento::where('profissional_id', $dados['profissional_id'])
                    ->where('status', '!=', 'cancelado')
                    ->where('inicio', '<', $dados['fim'])
                    ->where('fim', '>', $dados['inicio'])
                    ->exists();
            } else {
                $conflito = false;
            }

            if ($conflito) {
                throw new HorarioIndisponivelException('Horário não disponível.');
            }

            return Agendamento::create($dados);
        });
    }

    public function cancelar(Agendamento $agendamento): void
    {
        $agendamento->update(['status' => 'cancelado']);
    }

    public function buscarHorariosDisponiveis(Tenant $tenant, int $dias = 7): array
    {
        $profissionais = $tenant->profissionais()->where('ativo', true)->with('horarios')->get();
        $resultado = [];

        foreach ($profissionais as $profissional) {
            $resultado[$profissional->id] = [];

            for ($i = 0; $i < $dias; $i++) {
                $data = Carbon::today()->addDays($i);
                $slots = $profissional->slotsDisponiveis($data);
                $disponiveis = collect($slots)->where('disponivel', true)->pluck('hora')->values()->all();

                if (! empty($disponiveis)) {
                    $resultado[$profissional->id][$data->format('Y-m-d')] = $disponiveis;
                }
            }
        }

        return $resultado;
    }

    public function criarAgendamentoV2(Tenant $tenant, array $dados): Agendamento
    {
        return DB::transaction(function () use ($tenant, $dados) {
            // Tenant isolation: garantir que o profissional pertence ao tenant
            $profissional = Profissional::where('id', $dados['profissional_id'])
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            DB::select('SELECT pg_advisory_xact_lock(?)', [$dados['profissional_id']]);

            $servico = \App\Models\Servico::find($dados['servico_id'] ?? null);
            $duracao = $servico?->duracao_minutos ?? $dados['duracao_minutos'] ?? 30;

            $inicio = Carbon::parse("{$dados['data']} {$dados['horario']}");
            $fim = $inicio->copy()->addMinutes($duracao);

            if ($inicio->isPast()) {
                throw new HorarioIndisponivelException('Não é possível agendar para datas passadas.');
            }

            // Range overlap: detecta qualquer agendamento que se sobreponha ao slot [inicio, fim)
            $conflito = Agendamento::where('profissional_id', $dados['profissional_id'])
                ->whereNotIn('status', ['cancelado'])
                ->where('data_hora', '<', $fim)
                ->whereRaw("(data_hora + (duracao_minutos * INTERVAL '1 minute')) > ?", [$inicio])
                ->exists();

            if ($conflito) {
                throw new HorarioIndisponivelException('Horário não disponível.');
            }

            return Agendamento::create([
                'tenant_id'        => $tenant->id,
                'cliente_id'       => $dados['cliente_id'],
                'cliente_nome'     => $dados['cliente_nome'] ?? null,
                'cliente_telefone' => $dados['cliente_telefone'] ?? null,
                'profissional_id'  => $profissional->id,
                'servico_id'       => $dados['servico_id'] ?? null,
                'data_hora'        => $inicio,
                'inicio'           => $inicio,
                'fim'              => $fim,
                'duracao_minutos'  => $duracao,
                'status'           => 'agendado',
                'opcao_extra'      => $dados['opcao_extra'] ?? null,
                'observacoes'      => $dados['observacoes'] ?? null,
                'origem'           => $dados['origem'] ?? 'bot',
            ]);
        });
    }
}
