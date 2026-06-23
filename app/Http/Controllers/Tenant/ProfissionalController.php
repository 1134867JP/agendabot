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
            'profissionais' => $tenant->profissionais()->with('horarios')->get(),
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
        ]);
        $tenant->profissionais()->create($data);
        return back()->with('success', 'Profissional criado.');
    }

    public function update(Request $request, Profissional $profissional): RedirectResponse
    {
        abort_if($profissional->tenant_id !== app('tenant')->id, 403);
        $data = $request->validate([
            'nome'             => 'required|string|max:255',
            'especialidades'   => 'nullable|array',
            'especialidades.*' => 'string|max:100',
            'ativo'            => 'boolean',
        ]);
        $profissional->update($data);
        return back()->with('success', 'Profissional atualizado.');
    }

    public function destroy(Profissional $profissional): RedirectResponse
    {
        abort_if($profissional->tenant_id !== app('tenant')->id, 403);
        $profissional->update(['ativo' => false]);
        return back()->with('success', 'Profissional removido.');
    }
}
