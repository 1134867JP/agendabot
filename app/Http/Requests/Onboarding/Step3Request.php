<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class Step3Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nome_item'          => ['required', 'string', 'min:2', 'max:100'],
            'nome_servico'       => ['required', 'string', 'min:2', 'max:100'],
            'duracao_minutos'    => ['required', 'integer', Rule::in([15, 30, 45, 50, 60, 90, 120])],
            'valor'              => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'dias_atendimento'   => ['required', Rule::in(['segunda_sexta', 'segunda_sabado', 'todos'])],
            'hora_abertura'      => ['required', 'date_format:H:i'],
            'hora_fechamento'    => ['required', 'date_format:H:i'],
            'perfil_regras'      => ['required', Rule::in(['flexivel', 'equilibrado', 'protegido'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('hora_fechamento') <= $this->input('hora_abertura')) {
                $validator->errors()->add('hora_fechamento', 'O fechamento deve ser depois da abertura.');
            }
        });
    }
}
