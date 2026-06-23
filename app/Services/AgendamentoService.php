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
            DB::select('SELECT pg_advisory_xact_lock(?)', [$dados['recurso_id']]);

            $conflito = Agendamento::where('recurso_id', $dados['recurso_id'])
                ->where('status', '!=', 'cancelado')
                ->where('inicio', '<', $dados['fim'])
                ->where('fim', '>', $dados['inicio'])
                ->exists();

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
            DB::select('SELECT pg_advisory_xact_lock(?)', [$dados['profissional_id']]);

            $dataHora = Carbon::parse("{$dados['data']} {$dados['horario']}");

            $conflito = Agendamento::where('profissional_id', $dados['profissional_id'])
                ->whereNotIn('status', ['cancelado'])
                ->where('data_hora', $dataHora)
                ->exists();

            if ($conflito) {
                throw new HorarioIndisponivelException('Horário não disponível.');
            }

            $servico = \App\Models\Servico::find($dados['servico_id']);

            return Agendamento::create([
                'tenant_id'       => $tenant->id,
                'cliente_id'      => $dados['cliente_id'],
                'profissional_id' => $dados['profissional_id'],
                'servico_id'      => $dados['servico_id'] ?? null,
                'data_hora'       => $dataHora,
                'duracao_minutos' => $servico?->duracao_minutos ?? 30,
                'status'          => 'agendado',
                'opcao_extra'     => $dados['opcao_extra'] ?? null,
                'observacoes'     => $dados['observacoes'] ?? null,
                'origem'          => $dados['origem'] ?? 'bot',
            ]);
        });
    }
}
