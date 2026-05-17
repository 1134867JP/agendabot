<?php

namespace App\Services;

use App\Exceptions\HorarioIndisponivelException;
use App\Models\Agendamento;
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
}
