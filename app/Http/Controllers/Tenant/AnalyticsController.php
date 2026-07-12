<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\Conversa;
use App\Models\OperationalEvent;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');
        $hoje = now();
        $mesInicio = $hoje->copy()->startOfMonth();
        $inicioPeriodo = $hoje->copy()->subDays(29)->startOfDay();

        $base = Agendamento::where('tenant_id', $tenant->id)
            ->where('status', '!=', 'cancelado');

        $baseMes = (clone $base)->where(
            fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNotNull('data_hora')->where('data_hora', '>=', $mesInicio))
                ->orWhere(fn ($q2) => $q2->whereNull('data_hora')->where('inicio', '>=', $mesInicio))
        );

        $totalMes = (clone $baseMes)->count();
        $receitaMes = (clone $baseMes)->sum('valor_total');
        $conversasMes = Conversa::where('tenant_id', $tenant->id)->where('created_at', '>=', $mesInicio)->count();
        $agendWhatsapp = (clone $baseMes)->whereIn('origem', ['whatsapp', 'bot'])->count();
        $taxaConversao = $conversasMes > 0 ? round(($agendWhatsapp / $conversasMes) * 100, 1) : 0;
        $falhasIntegracao = OperationalEvent::where('tenant_id', $tenant->id)
            ->where('type', 'integration_failure')->where('created_at', '>=', $mesInicio)->count();
        $tempoResposta = (int) round((float) OperationalEvent::where('tenant_id', $tenant->id)
            ->where('type', 'bot_response')->where('created_at', '>=', $mesInicio)->avg('duration_ms'));
        $receitaBot = (float) (clone $baseMes)->whereIn('origem', ['whatsapp', 'bot'])->sum('valor_total');

        // Agendamentos por dia nos últimos 30 dias
        $porDiaRaw = (clone $base)
            ->where(
                fn ($q) => $q
                    ->where(fn ($q2) => $q2->whereNotNull('data_hora')->where('data_hora', '>=', $inicioPeriodo))
                    ->orWhere(fn ($q2) => $q2->whereNull('data_hora')->where('inicio', '>=', $inicioPeriodo))
            )
            ->selectRaw('DATE(COALESCE(data_hora, inicio)) as dia, COUNT(*) as total')
            ->groupBy('dia')
            ->orderBy('dia')
            ->pluck('total', 'dia');

        $porDia = collect(range(29, 0))->map(fn ($i) => [
            'data' => $hoje->copy()->subDays($i)->format('Y-m-d'),
            'label' => $hoje->copy()->subDays($i)->format('d/M'),
            'total' => (int) ($porDiaRaw[$hoje->copy()->subDays($i)->format('Y-m-d')] ?? 0),
        ])->values();

        // Top 5 serviços
        $topServicos = (clone $base)
            ->whereNotNull('servico_id')
            ->with('servico:id,nome')
            ->selectRaw('servico_id, COUNT(*) as total')
            ->groupBy('servico_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($a) => ['nome' => $a->servico?->nome ?? '–', 'total' => (int) $a->total]);

        // Horários de pico (top 3 horas)
        $picoHorario = (clone $base)
            ->selectRaw('EXTRACT(HOUR FROM COALESCE(data_hora, inicio))::int as hora, COUNT(*) as total')
            ->groupBy('hora')
            ->orderByDesc('total')
            ->limit(3)
            ->get()
            ->map(fn ($r) => ['nome' => sprintf('%02d:00', $r->hora), 'total' => (int) $r->total]);

        return Inertia::render('Tenant/Analytics', [
            'stats' => [
                'total_mes' => $totalMes,
                'receita_mes' => (float) $receitaMes,
                'conversas_mes' => $conversasMes,
                'taxa_conversao' => $taxaConversao,
                'falhas_integracao' => $falhasIntegracao,
                'tempo_resposta_ms' => $tempoResposta,
                'receita_bot' => $receitaBot,
            ],
            'por_dia' => $porDia,
            'top_servicos' => $topServicos,
            'pico_horario' => $picoHorario,
        ]);
    }
}
