<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Profissional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfissionalController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');
        return Inertia::render('Tenant/Profissionais/Index', [
            'profissionais'        => $tenant->profissionais()->with(['horarios', 'servicos:id,nome'])->get(),
            'servicos_disponiveis' => $tenant->servicos()->where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $request->validate([
            'nome'             => 'required|string|max:255',
            'especialidades'   => 'nullable|array',
            'especialidades.*' => 'string|max:100',
            'ativo'            => 'boolean',
            'servico_ids'      => 'nullable|array',
            'servico_ids.*'    => 'integer|exists:servicos,id',
        ]);
        $servicoIds = $data['servico_ids'] ?? [];
        $profissional = $tenant->profissionais()->create(\Illuminate\Support\Arr::except($data, ['servico_ids']));
        $profissional->servicos()->sync($servicoIds);
        return back()->with('success', 'Profissional criado.');
    }

    public function update(Request $request, Profissional $profissional): RedirectResponse
    {
        abort_if((int)$profissional->tenant_id !== (int)app('tenant')->id, 403);
        $data = $request->validate([
            'nome'             => 'required|string|max:255',
            'especialidades'   => 'nullable|array',
            'especialidades.*' => 'string|max:100',
            'ativo'            => 'boolean',
            'servico_ids'      => 'nullable|array',
            'servico_ids.*'    => 'integer|exists:servicos,id',
        ]);
        $servicoIds = $data['servico_ids'] ?? [];
        $profissional->update(\Illuminate\Support\Arr::except($data, ['servico_ids']));
        $profissional->servicos()->sync($servicoIds);
        return back()->with('success', 'Profissional atualizado.');
    }

    public function destroy(Profissional $profissional): RedirectResponse
    {
        abort_if((int)$profissional->tenant_id !== (int)app('tenant')->id, 403);
        $profissional->update(['ativo' => false]);
        return back()->with('success', 'Profissional removido.');
    }
}
