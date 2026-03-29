<?php

namespace App\Http\Requests\Investment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => ['required', Rule::in(['buy', 'sell', 'dividend'])],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'date'        => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'   => 'O tipo da movimentação é obrigatório.',
            'type.in'         => 'O tipo deve ser: buy, sell ou dividend.',
            'amount.required' => 'O valor é obrigatório.',
            'amount.min'      => 'O valor deve ser maior que zero.',
            'date.required'   => 'A data é obrigatória.',
        ];
    }
}
