<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'nome' => ['required', 'string', 'max:255'],
            'tipo_servico' => ['required', Rule::in(Tenant::TIPOS_SERVICO)],
            'tipo_servico_personalizado' => ['nullable', 'required_if:tipo_servico,personalizado', 'string', 'max:100'],
            'timezone' => ['required', Rule::in(Tenant::TIMEZONES)],
            'horarios_funcionamento' => ['nullable', 'string', 'max:255'],
            'horarios_funcionamento_semana' => ['required', 'array', 'size:7'],
            'horarios_funcionamento_semana.*.ativo' => ['required', 'boolean'],
            'horarios_funcionamento_semana.*.periodos' => ['present', 'array', 'max:2'],
            'horarios_funcionamento_semana.*.periodos.*.abertura' => ['required', 'date_format:H:i'],
            'horarios_funcionamento_semana.*.periodos.*.fechamento' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('horarios_funcionamento_semana', []) as $dia => $config) {
                if (empty($config['ativo'])) {
                    continue;
                }

                $periodos = $config['periodos'] ?? [];
                if (count($periodos) === 0) {
                    $validator->errors()->add(
                        "horarios_funcionamento_semana.{$dia}.periodos",
                        'Adicione pelo menos um período para o dia aberto.'
                    );

                    continue;
                }

                usort($periodos, fn (array $a, array $b) => ($a['abertura'] ?? '') <=> ($b['abertura'] ?? ''));
                $fimAnterior = null;

                foreach ($periodos as $periodo => $faixa) {
                    $abertura = $faixa['abertura'] ?? '';
                    $fechamento = $faixa['fechamento'] ?? '';

                    if ($abertura && $fechamento && $fechamento <= $abertura) {
                        $validator->errors()->add(
                            "horarios_funcionamento_semana.{$dia}.periodos.{$periodo}.fechamento",
                            'O fechamento deve ser depois da abertura.'
                        );
                    }

                    if ($fimAnterior && $abertura < $fimAnterior) {
                        $validator->errors()->add(
                            "horarios_funcionamento_semana.{$dia}.periodos.{$periodo}.abertura",
                            'Os períodos deste dia não podem se sobrepor.'
                        );
                    }

                    $fimAnterior = max($fimAnterior ?? '', $fechamento);
                }
            }
        });
    }
}
