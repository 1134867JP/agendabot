<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\CobrancaBot;
use App\Models\Tenant;
use App\Models\TokenUsage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceiroController extends Controller
{
    public function index(Request $request): Response
    {
        $mes = $request->input('mes', now()->format('Y-m'));
        $inicio = Carbon::parse($mes.'-01')->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $tenants = Tenant::where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'plano', 'subscription_status', 'taxa_agendamento_bot', 'isento_cobranca']);

        $rows = $tenants->map(function (Tenant $tenant) use ($inicio, $fim, $mes) {
            // Receita: mensalidade do plano
            $mensalidade = $tenant->isento_cobranca
                ? 0.0
                : (float) (config("plans.{$tenant->plano}.valor") ?? 0);

            // Receita: taxa variável de bot (cobranças registradas)
            $cobranca = CobrancaBot::where('tenant_id', $tenant->id)
                ->where('periodo', $mes)
                ->first();

            $receitaBot = $cobranca && $cobranca->status !== 'isento'
                ? (float) $cobranca->valor_total
                : 0.0;

            $receitaTotal = $mensalidade + $receitaBot;

            // Custo IA: soma dos tokens do período
            $tokens = TokenUsage::where('tenant_id', $tenant->id)
                ->whereBetween('created_at', [$inicio, $fim])
                ->selectRaw('
                    SUM(input_tokens)                 as input,
                    SUM(output_tokens)                as output,
                    SUM(cache_creation_input_tokens)  as cache_write,
                    SUM(cache_read_input_tokens)      as cache_read,
                    SUM(cost_usd)                     as cost_usd
                ')
                ->first();

            $custoUsd = (float) ($tokens->cost_usd ?? 0);
            $custoBrl = round($custoUsd * 5.70, 2); // cotação aproximada USD→BRL

            // Agendamentos via bot no período
            $agendamentosBot = Agendamento::where('tenant_id', $tenant->id)
                ->where('origem', 'bot')
                ->where('status', '!=', 'cancelado')
                ->whereBetween('created_at', [$inicio, $fim])
                ->count();

            $lucro = round($receitaTotal - $custoBrl, 2);
            $margem = $receitaTotal > 0
                ? round(($lucro / $receitaTotal) * 100, 1)
                : null;

            return [
                'id' => $tenant->id,
                'nome' => $tenant->nome,
                'plano' => $tenant->plano,
                'isento' => $tenant->isento_cobranca,
                'mensalidade' => $mensalidade,
                'receita_bot' => $receitaBot,
                'receita_total' => round($receitaTotal, 2),
                'custo_ia_brl' => $custoBrl,
                'lucro' => $lucro,
                'margem' => $margem,
                'agendamentos_bot' => $agendamentosBot,
                'tokens' => [
                    'input' => (int) ($tokens->input ?? 0),
                    'output' => (int) ($tokens->output ?? 0),
                    'cache_write' => (int) ($tokens->cache_write ?? 0),
                    'cache_read' => (int) ($tokens->cache_read ?? 0),
                ],
            ];
        });

        $totais = [
            'receita_total' => round($rows->sum('receita_total'), 2),
            'custo_ia_brl' => round($rows->sum('custo_ia_brl'), 2),
            'lucro' => round($rows->sum('lucro'), 2),
        ];

        return Inertia::render('SuperAdmin/Financeiro', [
            'mes' => $mes,
            'rows' => $rows->values(),
            'totais' => $totais,
        ]);
    }
}
