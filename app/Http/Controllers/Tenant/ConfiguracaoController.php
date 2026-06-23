<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguracaoController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Tenant/Configuracoes', [
            'tenant' => $tenant->only([
                'id',
                'nome',
                'tipo_servico',
                'tipo_servico_personalizado',
                'nome_agente',
                'tom_voz',
                'instrucoes_extras',
                'bot_ativo',
                'ramo_negocio',
                'descricao_negocio',
                'cidade',
                'endereco',
                'horarios_funcionamento',
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'nome'                       => ['required', 'string', 'max:255'],
            'tipo_servico'               => ['required', 'in:barbeiro,quadra,estetica,clinica,studio,personalizado'],
            'tipo_servico_personalizado' => ['nullable', 'required_if:tipo_servico,personalizado', 'string', 'max:100'],
        ]);

        $tenant->update($data);

        return back()->with('success', 'Configurações salvas.');
    }

    public function updateBot(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'ramo_negocio'              => 'nullable|string|max:255',
            'descricao_negocio'         => 'nullable|string|max:2000',
            'cidade'                    => 'nullable|string|max:100',
            'endereco'                  => 'nullable|string|max:255',
            'horarios_funcionamento'    => 'nullable|array',
            'nome_agente'               => 'required|string|max:50',
            'tom_voz'                   => 'required|in:formal,semiformal,descontraido',
            'instrucoes_extras'         => 'nullable|string|max:3000',
            'bot_ativo'                 => 'boolean',
        ]);

        $tenant->update($data);

        return back()->with('success', 'Configurações do bot salvas.');
    }
}
