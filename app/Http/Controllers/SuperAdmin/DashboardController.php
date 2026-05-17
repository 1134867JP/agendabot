<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Dashboard', [
            'stats' => [
                'total_tenants'      => Tenant::count(),
                'tenants_ativos'     => Tenant::where('ativo', true)->count(),
                'tenants_conectados' => Tenant::where('whatsapp_conectado', true)->count(),
                'agendamentos_hoje'  => Agendamento::whereDate('inicio', today())->count(),
                'agendamentos_mes'   => Agendamento::whereMonth('inicio', now()->month)->count(),
            ],
            'tenants' => Tenant::withCount(['agendamentos', 'recursos'])
                ->latest()
                ->paginate(20),
        ]);
    }
}
