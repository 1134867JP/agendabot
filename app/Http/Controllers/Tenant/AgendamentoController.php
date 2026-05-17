<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\Recurso;
use App\Services\AgendamentoService;
use Carbon\Carbon;
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

        $query = Agendamento::where('tenant_id', $tenant->id)
            ->with('recurso')
            ->orderBy('inicio', 'desc');

        if ($request->filled('data')) {
            $query->whereDate('inicio', $request->data);
        }
        if ($request->filled('recurso_id')) {
            $query->where('recurso_id', $request->recurso_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('busca')) {
            $query->where(function ($q) use ($request) {
                $q->where('cliente_nome', 'ilike', "%{$request->busca}%")
                  ->orWhere('cliente_telefone', 'like', "%{$request->busca}%");
            });
        }

        return Inertia::render('Tenant/Agendamentos/Index', [
            'tenant'       => $tenant,
            'agendamentos' => $query->paginate(20)->withQueryString(),
            'recursos'     => $tenant->recursos()->where('ativo', true)->get(),
            'filtros'      => $request->only(['data', 'recurso_id', 'status', 'busca']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $validated = $request->validate([
            'recurso_id'        => ['required', 'exists:recursos,id'],
            'cliente_nome'      => ['required', 'string', 'max:255'],
            'cliente_telefone'  => ['required', 'string', 'max:20'],
            'inicio'            => ['required', 'date'],
            'fim'               => ['required', 'date', 'after:inicio'],
            'observacoes'       => ['nullable', 'string'],
            'notificar_cliente' => ['boolean'],
        ]);

        $recurso = Recurso::where('tenant_id', $tenant->id)->findOrFail($validated['recurso_id']);

        $this->agendamentoService->criar([
            'tenant_id'        => $tenant->id,
            'recurso_id'       => $validated['recurso_id'],
            'cliente_nome'     => $validated['cliente_nome'],
            'cliente_telefone' => $validated['cliente_telefone'],
            'inicio'           => $validated['inicio'],
            'fim'              => $validated['fim'],
            'observacoes'      => $validated['observacoes'] ?? null,
            'origem'           => 'manual',
            'valor_total'      => $recurso->valor_hora
                ? $recurso->valor_hora * Carbon::parse($validated['inicio'])->diffInMinutes($validated['fim']) / 60
                : null,
        ]);

        return back()->with('success', 'Agendamento criado com sucesso.');
    }

    public function cancelar(Agendamento $agendamento): RedirectResponse
    {
        abort_unless($agendamento->tenant_id === app('tenant')->id, 403);
        $this->agendamentoService->cancelar($agendamento);
        return back()->with('success', 'Agendamento cancelado.');
    }

    public function concluir(Agendamento $agendamento): RedirectResponse
    {
        abort_unless($agendamento->tenant_id === app('tenant')->id, 403);
        $agendamento->update(['status' => 'concluido']);
        return back()->with('success', 'Agendamento concluído.');
    }
}
