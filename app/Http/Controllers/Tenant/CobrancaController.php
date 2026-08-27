<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use Illuminate\Http\JsonResponse;

class CobrancaController extends Controller
{
    public function resumo(): JsonResponse
    {
        $tenant = app('tenant');

        $agendamentosMes = Agendamento::where('tenant_id', $tenant->id)
            ->where('origem', 'bot')
            ->where('status', '!=', 'cancelado')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $estimativa = round($agendamentosMes * (float) $tenant->taxa_agendamento_bot, 2);

        $historico = $tenant->cobrancasBot()
            ->orderByDesc('periodo')
            ->limit(6)
            ->get(['periodo', 'quantidade_agendamentos', 'valor_total', 'status']);

        return response()->json([
            'agendamentos_mes' => $agendamentosMes,
            'taxa' => (float) $tenant->taxa_agendamento_bot,
            'estimativa' => $estimativa,
            'historico' => $historico,
        ]);
    }
}
