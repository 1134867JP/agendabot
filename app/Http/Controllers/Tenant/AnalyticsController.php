<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\Conversa;
use App\Models\OperationalEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = app('tenant');
        $dias = in_array((int) $request->integer('dias', 30), [7, 30, 90], true)
            ? (int) $request->integer('dias', 30)
            : 30;

        $fim = now()->endOfDay();
        $inicio = now()->subDays($dias - 1)->startOfDay();
        $fimAnterior = $inicio->copy()->subSecond();
        $inicioAnterior = $fimAnterior->copy()->subDays($dias - 1)->startOfDay();

        $noPeriodo = fn ($query, $de, $ate) => $query->where(
            fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNotNull('data_hora')->whereBetween('data_hora', [$de, $ate]))
                ->orWhere(fn ($q2) => $q2->whereNull('data_hora')->whereBetween('inicio', [$de, $ate]))
        );

        $base = Agendamento::where('tenant_id', $tenant->id)->where('status', '!=', 'cancelado');
        $atual = $noPeriodo(clone $base, $inicio, $fim);
        $anterior = $noPeriodo(clone $base, $inicioAnterior, $fimAnterior);

        $total = (clone $atual)->count();
        $totalAnterior = (clone $anterior)->count();
        $receita = (float) (clone $atual)->sum('valor_total');
        $receitaAnterior = (float) (clone $anterior)->sum('valor_total');

        $conversas = Conversa::where('tenant_id', $tenant->id)->whereBetween('created_at', [$inicio, $fim])->count();
        $conversasAnterior = Conversa::where('tenant_id', $tenant->id)->whereBetween('created_at', [$inicioAnterior, $fimAnterior])->count();

        $agendWhatsapp = (clone $atual)->whereIn('origem', ['whatsapp', 'bot'])->count();
        $agendWhatsappAnterior = (clone $anterior)->whereIn('origem', ['whatsapp', 'bot'])->count();
        $taxaConversao = $conversas > 0 ? round(($agendWhatsapp / $conversas) * 100, 1) : 0;
        $taxaConversaoAnterior = $conversasAnterior > 0 ? round(($agendWhatsappAnterior / $conversasAnterior) * 100, 1) : 0;

        $falhas = OperationalEvent::where('tenant_id', $tenant->id)
            ->where('type', 'integration_failure')
            ->whereBetween('created_at', [$inicio, $fim])
            ->count();

        $tempoResposta = (int) round((float) OperationalEvent::where('tenant_id', $tenant->id)
            ->where('type', 'bot_response')
            ->whereBetween('created_at', [$inicio, $fim])
            ->avg('duration_ms'));

        $receitaBot = (float) (clone $atual)->whereIn('origem', ['whatsapp', 'bot'])->sum('valor_total');

        $porDiaRaw = ($tenant->modo_bot ?? 'agendamento') === 'triagem'
            ? Conversa::where('tenant_id', $tenant->id)
                ->whereBetween('created_at', [$inicio, $fim])
                ->selectRaw('DATE(created_at) as dia, COUNT(*) as total')
                ->groupBy('dia')
                ->orderBy('dia')
                ->pluck('total', 'dia')
            : (clone $atual)
                ->selectRaw('DATE(COALESCE(data_hora, inicio)) as dia, COUNT(*) as total')
                ->groupBy('dia')
                ->orderBy('dia')
                ->pluck('total', 'dia');

        $porDia = collect(range($dias - 1, 0))->map(fn ($i) => [
            'data' => now()->subDays($i)->format('Y-m-d'),
            'label' => now()->subDays($i)->format('d/M'),
            'total' => (int) ($porDiaRaw[now()->subDays($i)->format('Y-m-d')] ?? 0),
        ])->values();

        $topServicos = (clone $atual)
            ->whereNotNull('servico_id')
            ->with('servico:id,nome')
            ->selectRaw('servico_id, COUNT(*) as total')
            ->groupBy('servico_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($appointment) => [
                'nome' => $appointment->servico?->nome ?? 'Sem nome',
                'total' => (int) $appointment->total,
            ]);

        $picoHorario = (clone $atual)
            ->selectRaw('EXTRACT(HOUR FROM COALESCE(data_hora, inicio))::int as hora, COUNT(*) as total')
            ->groupBy('hora')
            ->orderByDesc('total')
            ->limit(3)
            ->get()
            ->map(fn ($row) => [
                'nome' => sprintf('%02d:00', $row->hora),
                'total' => (int) $row->total,
            ]);

        $variacao = function (float $valor, float $anterior): float {
            if ($anterior == 0.0) return $valor > 0 ? 100 : 0;
            return round((($valor - $anterior) / abs($anterior)) * 100, 1);
        };

        return Inertia::render('Tenant/Analytics', [
            'modo' => $tenant->modo_bot ?? 'agendamento',
            'filtros' => ['dias' => $dias],
            'periodo' => [
                'inicio' => $inicio->toDateString(),
                'fim' => $fim->toDateString(),
            ],
            'stats' => [
                'total_mes' => $total,
                'receita_mes' => $receita,
                'conversas_mes' => $conversas,
                'taxa_conversao' => $taxaConversao,
                'falhas_integracao' => $falhas,
                'tempo_resposta_ms' => $tempoResposta,
                'receita_bot' => $receitaBot,
            ],
            'comparacao' => [
                'agendamentos' => $variacao($total, $totalAnterior),
                'receita' => $variacao($receita, $receitaAnterior),
                'conversas' => $variacao($conversas, $conversasAnterior),
                'conversao' => round($taxaConversao - $taxaConversaoAnterior, 1),
            ],
            'por_dia' => $porDia,
            'top_servicos' => $topServicos,
            'pico_horario' => $picoHorario,
        ]);
    }
}
