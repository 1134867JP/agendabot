<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TriagemController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Tenant/Triagem', [
            'config' => $tenant->triagemConfig(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'palavras_chave_humano'       => ['array', 'max:20'],
            'palavras_chave_humano.*'     => ['string', 'max:50'],
            'max_tentativas_sem_entender' => ['required', 'integer', 'min:1', 'max:10'],
            'transferir_fora_do_horario'  => ['boolean'],
            'mensagem_transferencia'      => ['nullable', 'string', 'max:300'],
        ]);

        $configuracoes = array_merge($tenant->configuracoes ?? [], [
            'triagem' => [
                'palavras_chave_humano'       => array_values(array_filter($data['palavras_chave_humano'] ?? [])),
                'max_tentativas_sem_entender' => $data['max_tentativas_sem_entender'],
                'transferir_fora_do_horario'  => $data['transferir_fora_do_horario'] ?? false,
                'mensagem_transferencia'      => $data['mensagem_transferencia'] ?? null,
            ],
        ]);

        $tenant->update(['configuracoes' => $configuracoes]);

        return back()->with('success', 'Regras de triagem salvas.');
    }
}
