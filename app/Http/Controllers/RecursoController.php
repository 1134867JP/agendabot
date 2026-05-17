<?php

namespace App\Http\Controllers;

use App\Models\Recurso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecursoController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('configuracoes.index');
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('configuracoes.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'nome'                    => ['required', 'string', 'max:255'],
            'descricao'               => ['nullable', 'string'],
            'valor_hora'              => ['nullable', 'numeric', 'min:0'],
            'duracao_padrao_minutos'  => ['required', 'integer', 'min:15'],
        ]);

        $tenant->recursos()->create($data);

        return back()->with('sucesso', 'Recurso criado.');
    }

    public function edit(Recurso $recurso): RedirectResponse
    {
        return redirect()->route('configuracoes.index');
    }

    public function update(Request $request, Recurso $recurso): RedirectResponse
    {
        abort_unless($recurso->tenant_id === app('tenant')->id, 403);

        $data = $request->validate([
            'nome'                    => ['required', 'string', 'max:255'],
            'descricao'               => ['nullable', 'string'],
            'valor_hora'              => ['nullable', 'numeric', 'min:0'],
            'duracao_padrao_minutos'  => ['required', 'integer', 'min:15'],
            'ativo'                   => ['boolean'],
        ]);

        $recurso->update($data);

        return back()->with('sucesso', 'Recurso atualizado.');
    }

    public function destroy(Recurso $recurso): RedirectResponse
    {
        abort_unless($recurso->tenant_id === app('tenant')->id, 403);

        $recurso->delete();

        return back()->with('sucesso', 'Recurso removido.');
    }
}
