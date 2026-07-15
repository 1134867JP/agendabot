<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app()->bound('tenant');
    }

    public function rules(): array
    {
        return [
            'ramo_negocio'      => ['nullable', 'string', 'max:255'],
            'descricao_negocio' => ['nullable', 'string', 'max:500'],
            'cidade'            => ['nullable', 'string', 'max:100'],
            'endereco'          => ['nullable', 'string', 'max:255'],
            'nome_agente'       => ['required', 'string', 'max:50'],
            'tom_voz'           => ['required', Rule::in(Tenant::TONS_VOZ)],
            'instrucoes_extras' => ['nullable', 'string', 'max:3000'],
            'bot_saudacao'      => ['nullable', 'string', 'max:500'],
            'bot_ativo'         => ['boolean'],
            'modo_bot'          => ['required', 'in:agendamento,triagem'],
            'horario_atendimento'              => ['nullable', 'array'],
            'horario_atendimento.*.ativo'      => ['boolean'],
            'horario_atendimento.*.abertura'   => ['nullable', 'required_if:horario_atendimento.*.ativo,true', 'date_format:H:i'],
            'horario_atendimento.*.fechamento' => ['nullable', 'required_if:horario_atendimento.*.ativo,true', 'date_format:H:i'],
            'mensagem_fora_horario' => ['nullable', 'string', 'max:500'],
            'lembrete_ativo'    => ['boolean'],
            'lembrete_texto'    => ['nullable', 'string', 'max:500'],
        ];
    }
}
