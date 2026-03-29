<?php

namespace App\Http\Requests\Investment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', Rule::in(['stock', 'crypto', 'fund', 'fixed_income'])],
            'institution' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do investimento é obrigatório.',
            'type.required' => 'O tipo do investimento é obrigatório.',
            'type.in'       => 'O tipo deve ser: stock, crypto, fund ou fixed_income.',
        ];
    }
}
