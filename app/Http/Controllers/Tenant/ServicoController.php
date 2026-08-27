<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Servico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServicoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tenant/Servicos/Index', [
            'servicos' => app('tenant')->servicos()
                ->with('profissionais:id,nome')
                ->orderBy('nome')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'valor_min' => 'nullable|numeric|min:0',
            'valor_max' => 'nullable|numeric|gte:valor_min',
            'duracao_minutos' => 'required|integer|min:5',
            'requer_avaliacao' => 'boolean',
            'ativo' => 'boolean',
        ]);
        app('tenant')->servicos()->create($data);

        return back()->with('success', 'Serviço criado.');
    }

    public function update(Request $request, Servico $servico): RedirectResponse
    {
        abort_if((int) $servico->tenant_id !== (int) app('tenant')->id, 403);
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'valor_min' => 'nullable|numeric|min:0',
            'valor_max' => 'nullable|numeric|gte:valor_min',
            'duracao_minutos' => 'required|integer|min:5',
            'requer_avaliacao' => 'boolean',
            'ativo' => 'boolean',
        ]);
        $servico->update($data);

        return back()->with('success', 'Serviço atualizado.');
    }

    public function destroy(Servico $servico): RedirectResponse
    {
        abort_if((int) $servico->tenant_id !== (int) app('tenant')->id, 403);
        $servico->delete();

        return back()->with('success', 'Serviço excluído.');
    }
}
