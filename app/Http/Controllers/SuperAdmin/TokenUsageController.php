<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\TokenUsage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TokenUsageController extends Controller
{
    public function index(): Response
    {
        $agora        = now();
        $inicioMes    = $agora->copy()->startOfMonth();
        $inicioMesAnt = $agora->copy()->subMonth()->startOfMonth();
        $fimMesAnt    = $agora->copy()->subMonth()->endOfMonth();

        // Totais do mês atual
        $mes = TokenUsage::where('created_at', '>=', $inicioMes)
            ->selectRaw('
                COUNT(*)                              AS calls,
                COALESCE(SUM(input_tokens), 0)                AS input,
                COALESCE(SUM(output_tokens), 0)               AS output,
                COALESCE(SUM(cache_creation_input_tokens), 0) AS cache_write,
                COALESCE(SUM(cache_read_input_tokens), 0)     AS cache_read
            ')
            ->first();

        // Totais do mês anterior (para comparação)
        $mesAnt = TokenUsage::whereBetween('created_at', [$inicioMesAnt, $fimMesAnt])
            ->selectRaw('
                COALESCE(SUM(input_tokens), 0)                AS input,
                COALESCE(SUM(output_tokens), 0)               AS output,
                COALESCE(SUM(cache_creation_input_tokens), 0) AS cache_write,
                COALESCE(SUM(cache_read_input_tokens), 0)     AS cache_read
            ')
            ->first();

        $custoMes    = TokenUsage::calcularCusto($mes->input, $mes->output, $mes->cache_write, $mes->cache_read);
        $custoMesAnt = TokenUsage::calcularCusto($mesAnt->input, $mesAnt->output, $mesAnt->cache_write, $mesAnt->cache_read);

        // Taxa de cache hit = cache_read / (input + cache_read) — evitar divisão por zero
        $totalInput = $mes->input + $mes->cache_read;
        $cacheHitRate = $totalInput > 0 ? round(($mes->cache_read / $totalInput) * 100, 1) : 0;

        // Economia gerada pelo cache (diferença de custo se tudo fossem tokens normais)
        $economiaCacheUsd = $mes->cache_read * (TokenUsage::PRECO_INPUT - TokenUsage::PRECO_CACHE_READ);

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
                COALESCE(SUM(cache_read_input_tokens), 0)     AS cache_read
            ')
            ->groupBy('tenants.id', 'tenants.nome', 'tenants.slug')
            ->orderByRaw('SUM(input_tokens + output_tokens) DESC')
            ->get()
            ->map(function ($row) {
                $row->custo_usd = TokenUsage::calcularCusto($row->input, $row->output, $row->cache_write, $row->cache_read);
                return $row;
            });

        // Por dia — últimos 30 dias
        $porDia = TokenUsage::where('token_usages.created_at', '>=', $agora->copy()->subDays(29)->startOfDay())
            ->selectRaw("
                DATE(created_at)                      AS dia,
                COUNT(*)                              AS calls,
                COALESCE(SUM(input_tokens), 0)                AS input,
                COALESCE(SUM(output_tokens), 0)               AS output,
                COALESCE(SUM(cache_read_input_tokens), 0)     AS cache_read
            ")
            ->groupByRaw('DATE(created_at)')
            ->orderBy('dia')
            ->get()
            ->map(function ($row) {
                $row->custo_usd = TokenUsage::calcularCusto($row->input, $row->output, 0, $row->cache_read);
                return $row;
            });

        return Inertia::render('SuperAdmin/TokenUsage', [
            'mes' => [
                'calls'       => (int) $mes->calls,
                'input'       => (int) $mes->input,
                'output'      => (int) $mes->output,
                'cache_write' => (int) $mes->cache_write,
                'cache_read'  => (int) $mes->cache_read,
                'custo_usd'   => $custoMes,
            ],
            'mesAnterior' => [
                'custo_usd' => $custoMesAnt,
            ],
            'cacheHitRate'     => $cacheHitRate,
            'economiaCacheUsd' => $economiaCacheUsd,
            'porTenant'        => $porTenant,
            'porDia'           => $porDia,
        ]);
    }
}
