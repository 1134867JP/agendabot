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
        $horario = $tenant->horarioAtendimentoTexto();

        return Inertia::render('Tenant/Triagem', [
            'config' => $tenant->triagemConfig(),
            'horarioFuncionamento' => [
                'configurado' => $horario !== '',
                'resumo' => $horario !== '' ? $horario : 'Horário ainda não configurado',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'palavras_chave_humano' => ['array', 'max:20'],
            'palavras_chave_humano.*' => ['string', 'max:50'],
            'max_tentativas_sem_entender' => ['required', 'integer', 'min:1', 'max:10'],
            'transferir_fora_do_horario' => ['boolean'],
            'mensagem_transferencia' => ['nullable', 'string', 'max:300'],
        ]);

        $palavrasChave = collect($data['palavras_chave_humano'] ?? [])
            ->map(fn (string $palavra) => trim(mb_strtolower($palavra)))
            ->filter()
            ->unique()
            ->take(20)
            ->values()
            ->all();

        $configuracoes = array_merge($tenant->configuracoes ?? [], [
            'triagem' => [
                'palavras_chave_humano' => $palavrasChave,
                'max_tentativas_sem_entender' => $data['max_tentativas_sem_entender'],
                'transferir_fora_do_horario' => $data['transferir_fora_do_horario'] ?? false,
                'mensagem_transferencia' => filled($data['mensagem_transferencia'] ?? null)
                    ? trim($data['mensagem_transferencia'])
                    : null,
            ],
        ]);

        $tenant->update(['configuracoes' => $configuracoes]);

        return back()->with('success', 'Regras de triagem salvas.');
    }
}
