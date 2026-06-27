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
                $servico = \App\Models\Servico::where('id', $dados['servico_id'])
                    ->where('tenant_id', $tenant->id)
                    ->first();
            }
            $duracao = $servico?->duracao_minutos ?? $dados['duracao_minutos'] ?? 30;

            // Timezone explícito para garantir que "10:00" seja interpretado como horário local
            $tz      = config('app.timezone', 'America/Sao_Paulo');
            $horario = substr($dados['horario'], 0, 5); // garante formato HH:MM (descarta segundos se vierem)
            $inicio  = Carbon::createFromFormat('Y-m-d H:i', "{$dados['data']} {$horario}", $tz);
            $fim    = $inicio->copy()->addMinutes($duracao);

            if ($inicio->isPast()) {
                throw new HorarioIndisponivelException('Não é possível agendar para datas passadas.');
            }

            // Range overlap: detecta qualquer agendamento que se sobreponha ao slot [inicio, fim)
            $conflito = Agendamento::where('profissional_id', $profissionalId)
                ->whereNotIn('status', ['cancelado'])
                ->where('data_hora', '<', $fim)
                ->whereRaw("(data_hora + (duracao_minutos * INTERVAL '1 minute')) > ?", [$inicio])
                ->exists();

            if ($conflito) {
                throw new HorarioIndisponivelException('Horário não disponível.');
            }

            $agendamento = Agendamento::create([
                'tenant_id'        => $tenant->id,
                'cliente_id'       => $dados['cliente_id'],
                'cliente_nome'     => $dados['cliente_nome'],
                'cliente_telefone' => $dados['cliente_telefone'],
                'profissional_id'  => $profissional->id,
                'servico_id'       => $servico?->id,
                'data_hora'        => $inicio,
                'inicio'           => $inicio,
                'fim'              => $fim,
                'duracao_minutos'  => $duracao,
                'status'           => 'agendado',
                'opcao_extra'      => $dados['opcao_extra'] ?? null,
                'observacoes'      => $dados['observacoes'] ?? null,
                'origem'           => $dados['origem'] ?? 'bot',
            ]);

            \Illuminate\Support\Facades\Log::channel('db')->info('AGENDAMENTO_INSERT', [
                'id'               => $agendamento->id,
                'tenant_id'        => $tenant->id,
                'tenant'           => $tenant->nome,
                'cliente_nome'     => $agendamento->cliente_nome,
                'cliente_telefone' => $agendamento->cliente_telefone,
                'profissional_id'  => $agendamento->profissional_id,
                'servico_id'       => $agendamento->servico_id,
                'data_hora_brt'    => $inicio->toDateTimeString(),
                'data_hora_utc'    => $inicio->utc()->toDateTimeString(),
                'duracao_minutos'  => $duracao,
                'status'           => 'agendado',
                'origem'           => $agendamento->origem,
            ]);

            return $agendamento;
        });
    }
}
