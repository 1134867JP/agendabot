<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConfiguracaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // O acesso ao tenant já é garantido pelo middleware da rota.
        return app()->bound('tenant');
    }

    public function rules(): array
    {
        return [
            'nome'                       => ['required', 'string', 'max:255'],
            'tipo_servico'               => ['required', Rule::in(Tenant::TIPOS_SERVICO)],
            'tipo_servico_personalizado' => ['nullable', 'required_if:tipo_servico,personalizado', 'string', 'max:100'],
            'horarios_funcionamento'     => ['nullable', 'string', 'max:255'],
        ];
    }
}
