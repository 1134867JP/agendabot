<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\OpcaoExtra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpcaoExtraController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tenant/OpcaoExtra/Index', [
            'opcoes' => app('tenant')->opcoes_extras()->orderBy('tipo')->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tipo'  => 'required|in:convenio,pagamento,outro',
            'nome'  => 'required|string|max:255',
            'ativo' => 'boolean',
        ]);
        app('tenant')->opcoes_extras()->create($data);
        return back()->with('success', 'Opção criada.');
    }

    public function update(Request $request, OpcaoExtra $opcaoExtra): RedirectResponse
    {
        abort_if((int)$opcaoExtra->tenant_id !== (int)app('tenant')->id, 403);
        $opcaoExtra->update($request->validate([
            'tipo'  => 'required|in:convenio,pagamento,outro',
            'nome'  => 'required|string|max:255',
            'ativo' => 'boolean',
        ]));
        return back()->with('success', 'Opção atualizada.');
    }

    public function destroy(OpcaoExtra $opcaoExtra): RedirectResponse
    {
        abort_if((int)$opcaoExtra->tenant_id !== (int)app('tenant')->id, 403);
        $opcaoExtra->update(['ativo' => false]);
        return back()->with('success', 'Opção removida.');
    }
}
