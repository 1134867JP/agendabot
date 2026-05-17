<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Recurso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecursoController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Tenant/Recursos/Index', [
            'tenant'   => $tenant,
            'recursos' => $tenant->recursos()->with('horariosFuncionamento')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'nome'                   => ['required', 'string', 'max:255'],
            'descricao'              => ['nullable', 'string'],
            'valor_hora'             => ['nullable', 'numeric', 'min:0'],
            'duracao_padrao_minutos' => ['required', 'integer', 'min:15'],
        ]);

        $tenant->recursos()->create($data);

        return back()->with('success', 'Recurso criado.');
    }

    public function update(Request $request, Recurso $recurso): RedirectResponse
    {
        abort_unless($recurso->tenant_id === app('tenant')->id, 403);

        $data = $request->validate([
            'nome'                   => ['required', 'string', 'max:255'],
            'descricao'              => ['nullable', 'string'],
            'valor_hora'             => ['nullable', 'numeric', 'min:0'],
            'duracao_padrao_minutos' => ['required', 'integer', 'min:15'],
            'ativo'                  => ['boolean'],
        ]);

        $recurso->update($data);

        return back()->with('success', 'Recurso atualizado.');
    }

    public function destroy(Recurso $recurso): RedirectResponse
    {
        abort_unless($recurso->tenant_id === app('tenant')->id, 403);
        $recurso->delete();
        return back()->with('success', 'Recurso removido.');
    }

    public function create(): Response
    {
        return $this->index();
    }

    public function edit(Recurso $recurso): Response
    {
        return $this->index();
    }
}
