<?php

namespace App\Http\Requests\Onboarding;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class Step1Request extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nome_usuario' => trim((string) $this->input('nome_usuario')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'nome_estabelecimento' => trim((string) $this->input('nome_estabelecimento')),
            'tipo_servico_personalizado' => trim((string) $this->input('tipo_servico_personalizado')) ?: null,
            'telefone' => preg_replace('/\D+/', '', (string) $this->input('telefone')),
        ]);
    }

    public function authorize(): bool
    {
        // Cadastro público — sem autenticação prévia.
        return true;
    }

    public function rules(): array
    {
        return [
            'nome_usuario' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'senha' => ['required', 'min:8', 'confirmed'],
            'nome_estabelecimento' => ['required', 'string', 'min:2', 'max:255'],
            'tipo_servico' => ['required', Rule::in(Tenant::TIPOS_SERVICO)],
            'tipo_servico_personalizado' => ['nullable', 'required_if:tipo_servico,personalizado', 'string', 'max:100'],
            'telefone' => ['required', 'string', 'regex:/^(?:55)?[1-9][0-9]{9,10}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'telefone.regex' => 'Informe um WhatsApp válido com DDD, por exemplo: (51) 99999-9999.',
            'senha.confirmed' => 'Revise a senha informada.',
        ];
    }
}
