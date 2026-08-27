<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegraAgendamentoController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Tenant/RegrasAgendamento', [
            'config' => $tenant->regrasAgendamentoConfig(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'antecedencia_minima_minutos' => ['required', 'integer', 'min:0', 'max:10080'],
            'antecedencia_maxima_dias' => ['required', 'integer', 'min:1', 'max:365'],
            'buffer_entre_agendamentos_minutos' => ['required', 'integer', 'min:0', 'max:240'],
            'permite_cliente_remarcar' => ['boolean'],
            'permite_cliente_cancelar' => ['boolean'],
            'politica_cancelamento' => ['nullable', 'string', 'max:500'],
        ]);

        $configuracoes = array_merge($tenant->configuracoes ?? [], [
            'regras_agendamento' => [
                'antecedencia_minima_minutos' => $data['antecedencia_minima_minutos'],
                'antecedencia_maxima_dias' => $data['antecedencia_maxima_dias'],
                'buffer_entre_agendamentos_minutos' => $data['buffer_entre_agendamentos_minutos'],
                'permite_cliente_remarcar' => $data['permite_cliente_remarcar'] ?? true,
                'permite_cliente_cancelar' => $data['permite_cliente_cancelar'] ?? true,
                'politica_cancelamento' => $data['politica_cancelamento'] ?? null,
            ],
        ]);

        $tenant->update(['configuracoes' => $configuracoes]);

        return back()->with('success', 'Regras de agendamento salvas.');
    }
}
