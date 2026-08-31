<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\OperationalEvent;
use App\Models\TokenUsage;
use App\Support\AiStatus;
use Inertia\Inertia;
use Inertia\Response;

class TokenUsageController extends Controller
{
    public function index(): Response
    {
        $agora = now();
        $inicioMes = $agora->copy()->startOfMonth();
        $inicioMesAnt = $agora->copy()->subMonth()->startOfMonth();
        $fimMesAnt = $agora->copy()->subMonth()->endOfMonth();

        // Totais do mês atual
        $mes = TokenUsage::where('created_at', '>=', $inicioMes)
            ->selectRaw('
                COUNT(*)                              AS calls,
                COALESCE(SUM(input_tokens), 0)                AS input,
                COALESCE(SUM(output_tokens), 0)               AS output,
                COALESCE(SUM(cache_creation_input_tokens), 0) AS cache_write,
                COALESCE(SUM(cache_read_input_tokens), 0)     AS cache_read,
                COALESCE(SUM(cost_usd), 0)                    AS cost_usd
            ')
            ->first();

        // Totais do mês anterior (para comparação)
        $mesAnt = TokenUsage::whereBetween('created_at', [$inicioMesAnt, $fimMesAnt])
            ->selectRaw('
                COALESCE(SUM(input_tokens), 0)                AS input,
                COALESCE(SUM(output_tokens), 0)               AS output,
                COALESCE(SUM(cache_creation_input_tokens), 0) AS cache_write,
                COALESCE(SUM(cache_read_input_tokens), 0)     AS cache_read,
                COALESCE(SUM(cost_usd), 0)                    AS cost_usd
            ')
            ->first();

        $custoMes = (float) $mes->cost_usd;
        $custoMesAnt = (float) $mesAnt->cost_usd;

        // Taxa de cache hit = cache_read / (input + cache_read) — evitar divisão por zero
        $totalInput = $mes->input + $mes->cache_read;
        $cacheHitRate = $totalInput > 0 ? round(($mes->cache_read / $totalInput) * 100, 1) : 0;

        // Economia gerada pelo cache, respeitando o preço de cada provider/modelo.
        $economiaCacheUsd = TokenUsage::where('created_at', '>=', $inicioMes)
            ->selectRaw('provider, model, COALESCE(SUM(cache_read_input_tokens), 0) AS cache_read')
            ->groupBy('provider', 'model')
            ->get()
            ->sum(function (TokenUsage $usage): float {
                $pricing = TokenUsage::precosModelo($usage->provider, $usage->model);

                return ((int) $usage->cache_read / 1_000_000)
                    * max(0, $pricing['input'] - $pricing['cache_read']);
            });

        // Por tenant — mês atual
        $porTenant = TokenUsage::where('token_usages.created_at', '>=', $inicioMes)
            ->join('tenants', 'tenants.id', '=', 'token_usages.tenant_id')
            ->selectRaw('
                tenants.id,
                tenants.nome,
                tenants.slug,
                COUNT(*)                              AS calls,
                COALESCE(SUM(input_tokens), 0)                AS input,
                COALESCE(SUM(output_tokens), 0)               AS output,
                COALESCE(SUM(cache_creation_input_tokens), 0) AS cache_write,
                COALESCE(SUM(cache_read_input_tokens), 0)     AS cache_read,
                COALESCE(SUM(cost_usd), 0)                    AS cost_usd
            ')
            ->groupBy('tenants.id', 'tenants.nome', 'tenants.slug')
            ->orderByRaw('SUM(input_tokens + output_tokens) DESC')
            ->get()
            ->map(function ($row) {
                $row->custo_usd = (float) $row->cost_usd;

                return $row;
            });

        // Por provider/modelo — mês atual. É esta tabela que responde "quanto cada
        // modelo está gastando": com múltiplos providers em fallback (ver config/ai.php),
        // o card de "Custo do mês" sozinho não mostra quem está consumindo o quê.
        $falhasPorProvider = OperationalEvent::where('created_at', '>=', $inicioMes)
            ->where('type', 'integration_failure')
            ->selectRaw('provider, COUNT(*) AS falhas')
            ->groupBy('provider')
            ->pluck('falhas', 'provider');

        $porProvider = TokenUsage::where('created_at', '>=', $inicioMes)
            ->selectRaw('
                provider,
                model,
                COUNT(*)                              AS calls,
                COALESCE(SUM(input_tokens), 0)                AS input,
                COALESCE(SUM(output_tokens), 0)               AS output,
                COALESCE(SUM(cache_creation_input_tokens), 0) AS cache_write,
                COALESCE(SUM(cache_read_input_tokens), 0)     AS cache_read,
                COALESCE(SUM(cost_usd), 0)                    AS cost_usd,
                COALESCE(AVG(latency_ms), 0)                  AS latencia_media_ms
            ')
            ->groupBy('provider', 'model')
            ->orderByRaw('SUM(cost_usd) DESC')
            ->get()
            ->map(function ($row) use ($falhasPorProvider) {
                $row->custo_usd = (float) $row->cost_usd;
                $row->latencia_media_ms = (int) round($row->latencia_media_ms);
                $row->falhas = (int) ($falhasPorProvider[$row->provider] ?? 0);

                return $row;
            });

        // Por dia — últimos 30 dias
        $porDia = TokenUsage::where('token_usages.created_at', '>=', $agora->copy()->subDays(29)->startOfDay())
            ->selectRaw('
                DATE(created_at)                      AS dia,
                COUNT(*)                              AS calls,
                COALESCE(SUM(input_tokens), 0)                AS input,
                COALESCE(SUM(output_tokens), 0)               AS output,
                COALESCE(SUM(cache_read_input_tokens), 0)     AS cache_read,
                COALESCE(SUM(cost_usd), 0)                    AS cost_usd
            ')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('dia')
            ->get()
            ->map(function ($row) {
                $row->custo_usd = (float) $row->cost_usd;

                return $row;
            });

        return Inertia::render('SuperAdmin/TokenUsage', [
            'ia' => AiStatus::resumo(),
            'mes' => [
                'calls' => (int) $mes->calls,
                'input' => (int) $mes->input,
                'output' => (int) $mes->output,
                'cache_write' => (int) $mes->cache_write,
                'cache_read' => (int) $mes->cache_read,
                'custo_usd' => $custoMes,
            ],
            'mesAnterior' => [
                'custo_usd' => $custoMesAnt,
            ],
            'cacheHitRate' => $cacheHitRate,
            'economiaCacheUsd' => $economiaCacheUsd,
            'porTenant' => $porTenant,
            'porProvider' => $porProvider,
            'porDia' => $porDia,
        ]);
    }
}
