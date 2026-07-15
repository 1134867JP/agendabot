<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\CobrancaBot;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        $profissionalAtivo = $tenant->profissionais()->where('ativo', true)->first();

        $recursoComHorario = $tenant->recursos()->where('ativo', true)
            ->whereHas('horariosFuncionamento')->exists();

        $setupCompleto = [
            'profissionais' => $tenant->profissionais()->where('ativo', true)->exists(),
            'servicos'      => $tenant->servicos()->where('ativo', true)->exists(),
            'recursos'      => $tenant->recursos()->where('ativo', true)->exists(),
            'whatsapp'      => (bool) $tenant->whatsapp_conectado,
            'bot_config'    => ! empty($tenant->ramo_negocio),
            'horario'       => ($profissionalAtivo && $profissionalAtivo->horarios()->exists())
                || $recursoComHorario,
        ];

        return Inertia::render('Tenant/Dashboard', [
            'tenant' => $tenant,
            'stats'  => [
                'agendamentos_hoje'   => Agendamento::where('tenant_id', $tenant->id)
                    ->whereDate('inicio', today())
                    ->where('status', 'confirmado')
                    ->count(),
                'agendamentos_semana' => Agendamento::where('tenant_id', $tenant->id)
                    ->whereBetween('inicio', [now()->startOfWeek(), now()->endOfWeek()])
                    ->where('status', 'confirmado')
                    ->count(),
                'receita_mes'         => Agendamento::where('tenant_id', $tenant->id)
                    ->whereMonth('inicio', now()->month)
                    ->where('status', '!=', 'cancelado')
                    ->sum('valor_total'),
                'whatsapp_conectado'  => $tenant->whatsapp_conectado,
                'bot_agendamentos_mes' => Agendamento::where('tenant_id', $tenant->id)
                    ->where('origem', 'bot')
                    ->where('status', '!=', 'cancelado')
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'bot_taxa'             => (float) $tenant->taxa_agendamento_bot,
            ],
            'ultima_cobranca_bot' => \Illuminate\Support\Facades\Schema::hasTable('cobrancas_bot')
                ? CobrancaBot::where('tenant_id', $tenant->id)->orderByDesc('periodo')->first(['periodo', 'quantidade_agendamentos', 'valor_total', 'status'])
                : null,
            'proximos_agendamentos' => Agendamento::where('tenant_id', $tenant->id)
                ->with('recurso')
                ->where('inicio', '>=', now())
                ->where('status', 'confirmado')
                ->orderBy('inicio')
                ->limit(5)
                ->get(),
            'setup_completo' => $setupCompleto,
        ]);
    }
}
