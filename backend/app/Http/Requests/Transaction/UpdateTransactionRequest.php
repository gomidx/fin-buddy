<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => ['sometimes', Rule::in(['income', 'expense'])],
            'amount'      => ['sometimes', 'numeric', 'min:0.01'],
            'category_id' => [
                'sometimes',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where(function ($q) {
                        $q->whereNull('user_id')
                          ->orWhere('user_id', $this->user()->id);
                    });
                }),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'date'        => ['sometimes', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in'            => 'O tipo deve ser "income" ou "expense".',
            'amount.numeric'     => 'O valor deve ser numérico.',
            'amount.min'         => 'O valor deve ser maior que zero.',
            'category_id.exists' => 'Categoria inválida ou inacessível.',
            'date.date'          => 'Informe uma data válida.',
        ];
    }
}
