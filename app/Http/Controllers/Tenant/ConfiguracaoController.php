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
        $cfg    = $tenant->configuracoes ?? [];

        return Inertia::render('Tenant/Configuracoes', [
            'tenant' => array_merge($tenant->only([
                'id',
                'nome',
                'tipo_servico',
                'tipo_servico_personalizado',
                'nome_agente',
                'tom_voz',
                'instrucoes_extras',
                'bot_saudacao',
                'bot_ativo',
                'modo_bot',
                'horario_atendimento',
                'mensagem_fora_horario',
                'ramo_negocio',
                'descricao_negocio',
                'cidade',
                'endereco',
                'horarios_funcionamento',
            ]), [
                'lembrete_ativo' => $cfg['lembrete_ativo'] ?? true,
                'lembrete_texto' => $cfg['lembrete_texto'] ?? '',
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
            'horarios_funcionamento'     => ['nullable', 'string', 'max:255'],
        ]);

        $tenant->update($data);

        return back()->with('success', 'Configurações salvas.');
    }

    public function updateBot(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'ramo_negocio'      => 'nullable|string|max:255',
            'descricao_negocio' => 'nullable|string|max:500',
            'cidade'            => 'nullable|string|max:100',
            'endereco'          => 'nullable|string|max:255',
            'nome_agente'       => 'required|string|max:50',
            'tom_voz'           => 'required|in:formal,semiformal,descontraido',
            'instrucoes_extras' => 'nullable|string|max:3000',
            'bot_saudacao'      => 'nullable|string|max:500',
            'bot_ativo'         => 'boolean',
            'modo_bot'          => 'required|in:agendamento,triagem',
            'horario_atendimento'              => 'nullable|array',
            'horario_atendimento.*.ativo'      => 'boolean',
            'horario_atendimento.*.abertura'   => 'nullable|required_if:horario_atendimento.*.ativo,true|date_format:H:i',
            'horario_atendimento.*.fechamento' => 'nullable|required_if:horario_atendimento.*.ativo,true|date_format:H:i',
            'mensagem_fora_horario' => 'nullable|string|max:500',
            'lembrete_ativo'    => 'boolean',
            'lembrete_texto'    => 'nullable|string|max:500',
        ]);

        $configuracoes = array_merge($tenant->configuracoes ?? [], [
            'lembrete_ativo' => $data['lembrete_ativo'] ?? true,
            'lembrete_texto' => $data['lembrete_texto'] ?? null,
        ]);

        unset($data['lembrete_ativo'], $data['lembrete_texto']);

        $tenant->update(array_merge($data, ['configuracoes' => $configuracoes]));

        return back()->with('success', 'Configurações do bot salvas.');
    }
}
