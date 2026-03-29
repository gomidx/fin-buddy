<?php

namespace App\Http\Requests\Investment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvestmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:255'],
            'type'        => ['sometimes', Rule::in(['stock', 'crypto', 'fund', 'fixed_income'])],
            'institution' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'O tipo deve ser: stock, crypto, fund ou fixed_income.',
        ];
    }
}
