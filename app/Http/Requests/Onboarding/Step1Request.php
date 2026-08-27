<?php

namespace App\Http\Requests\Onboarding;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Step1Request extends FormRequest
{
    public function authorize(): bool
    {
        // Cadastro público — sem autenticação prévia.
        return true;
    }

    public function rules(): array
    {
        return [
            'nome_usuario' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'senha' => ['required', 'min:8', 'confirmed'],
            'nome_estabelecimento' => ['required', 'string', 'max:255'],
            'tipo_servico' => ['required', Rule::in(Tenant::TIPOS_SERVICO)],
            'tipo_servico_personalizado' => ['nullable', 'required_if:tipo_servico,personalizado', 'string', 'max:100'],
            'telefone' => ['required', 'string', 'min:10', 'max:25'],
        ];
    }
}
