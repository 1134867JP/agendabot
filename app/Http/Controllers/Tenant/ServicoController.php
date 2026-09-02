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
        $tenant = app('tenant');

        return Inertia::render('Tenant/Servicos/Index', [
            'agenda' => $tenant->agendaConfig(),
            'servicos' => $tenant->servicos()
                ->with(['profissionais:id,nome', 'recursos:id,nome'])
                ->orderBy('nome')
                ->get(),
            'recursos' => $tenant->recursos()->where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'valor_min' => 'nullable|numeric|min:0',
            'valor_max' => 'nullable|numeric|gte:valor_min',
            'duracao_minutos' => 'required|integer|min:5',
            'requer_profissional' => 'boolean',
            'requer_recurso' => 'boolean',
            'recurso_ids' => ['array'],
            'recurso_ids.*' => ['integer', \Illuminate\Validation\Rule::exists('recursos', 'id')->where('tenant_id', $tenant->id)],
            'requer_avaliacao' => 'boolean',
            'ativo' => 'boolean',
        ]);
        $recursoIds = $data['recurso_ids'] ?? [];
        unset($data['recurso_ids']);
        $servico = $tenant->servicos()->create($data);
        $servico->recursos()->sync($recursoIds);

        return back()->with('success', 'Serviço criado.');
    }

    public function update(Request $request, Servico $servico): RedirectResponse
    {
        abort_if((int) $servico->tenant_id !== (int) app('tenant')->id, 403);
        $tenant = app('tenant');
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'valor_min' => 'nullable|numeric|min:0',
            'valor_max' => 'nullable|numeric|gte:valor_min',
            'duracao_minutos' => 'required|integer|min:5',
            'requer_profissional' => 'boolean',
            'requer_recurso' => 'boolean',
            'recurso_ids' => ['array'],
            'recurso_ids.*' => ['integer', \Illuminate\Validation\Rule::exists('recursos', 'id')->where('tenant_id', $tenant->id)],
            'requer_avaliacao' => 'boolean',
            'ativo' => 'boolean',
        ]);
        $hasRecursoIds = array_key_exists('recurso_ids', $data);
        $recursoIds = $data['recurso_ids'] ?? [];
        unset($data['recurso_ids']);
        $servico->update($data);
        if ($hasRecursoIds) {
            $servico->recursos()->sync($recursoIds);
        }

        return back()->with('success', 'Serviço atualizado.');
    }

    public function destroy(Servico $servico): RedirectResponse
    {
        abort_if((int) $servico->tenant_id !== (int) app('tenant')->id, 403);
        $servico->delete();

        return back()->with('success', 'Serviço excluído.');
    }
}
