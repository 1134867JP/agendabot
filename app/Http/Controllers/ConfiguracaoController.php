<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguracaoController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Configuracoes/Index', [
            'tenant'   => $tenant,
            'recursos' => $tenant->recursos()->with('horariosFuncionamento')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'nome'         => ['required', 'string', 'max:255'],
            'tipo_servico' => ['required', 'in:barbeiro,quadra,estetica,personalizado'],
        ]);

        $tenant->update($data);

        return back()->with('sucesso', 'Configurações salvas.');
    }
}
