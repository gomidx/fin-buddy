<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => ['required', Rule::in(['income', 'expense'])],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where(function ($q) {
                        $q->whereNull('user_id')
                          ->orWhere('user_id', $this->user()->id);
                    });
                }),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'date'        => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'        => 'O tipo da transação é obrigatório.',
            'type.in'              => 'O tipo deve ser "income" ou "expense".',
            'amount.required'      => 'O valor é obrigatório.',
            'amount.numeric'       => 'O valor deve ser numérico.',
            'amount.min'           => 'O valor deve ser maior que zero.',
            'category_id.required' => 'A categoria é obrigatória.',
            'category_id.exists'   => 'Categoria inválida ou inacessível.',
            'date.required'        => 'A data é obrigatória.',
            'date.date'            => 'Informe uma data válida.',
        ];
    }
}
