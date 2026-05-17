<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Services\AgendamentoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgendamentoController extends Controller
{
    public function __construct(private AgendamentoService $agendamentoService) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $query = $tenant->agendamentos()
            ->with('recurso')
            ->orderBy('inicio', 'desc');

        if ($request->filled('data')) {
            $query->whereDate('inicio', $request->data);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return Inertia::render('Agendamentos/Index', [
            'tenant'       => $tenant,
            'agendamentos' => $query->paginate(20)->withQueryString(),
            'filtros'      => $request->only(['data', 'status']),
        ]);
    }

    public function cancelar(Agendamento $agendamento): RedirectResponse
    {
        abort_unless($agendamento->tenant_id === app('tenant')->id, 403);

        $this->agendamentoService->cancelar($agendamento);

        return back()->with('sucesso', 'Agendamento cancelado.');
    }
}
