<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

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
            ],
            'proximos_agendamentos' => Agendamento::where('tenant_id', $tenant->id)
                ->with('recurso')
                ->where('inicio', '>=', now())
                ->where('status', 'confirmado')
                ->orderBy('inicio')
                ->limit(5)
                ->get(),
        ]);
    }
}
