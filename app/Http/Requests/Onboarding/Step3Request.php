<?php

namespace App\Http\Requests\Onboarding;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Step3Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'bot_nome'     => ['required', 'string', 'min:2', 'max:80'],
            'bot_saudacao' => ['required', 'string', 'min:10', 'max:500'],
            'bot_tom'      => ['required', Rule::in(Tenant::TONS_VOZ)],
        ];
    }
}
