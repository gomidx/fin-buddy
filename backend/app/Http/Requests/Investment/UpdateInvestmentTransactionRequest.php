<?php

namespace App\Http\Requests\Investment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvestmentTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => ['sometimes', Rule::in(['buy', 'sell', 'dividend'])],
            'amount'      => ['sometimes', 'numeric', 'min:0.01'],
            'date'        => ['sometimes', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in'    => 'O tipo deve ser: buy, sell ou dividend.',
            'amount.min' => 'O valor deve ser maior que zero.',
        ];
    }
}
