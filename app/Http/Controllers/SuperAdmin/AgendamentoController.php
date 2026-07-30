<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgendamentoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Agendamento::with(['recurso', 'profissional', 'servico', 'tenant'])
            ->orderBy('inicio', 'desc');

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }
        if ($request->filled('data')) {
            $query->whereDate('inicio', $request->data);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return Inertia::render('SuperAdmin/Agendamentos', [
            'agendamentos' => $query->paginate(30)->withQueryString(),
            'tenants' => Tenant::select('id', 'nome')->orderBy('nome')->get(),
            'filtros' => $request->only(['tenant_id', 'data', 'status']),
        ]);
    }
}
