<?php

namespace App\Http\Requests\RecurringTransaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecurringTransactionRequest extends FormRequest
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
            'description' => ['sometimes', 'string', 'max:255'],
            'frequency'   => ['sometimes', Rule::in(['monthly', 'yearly'])],
            'start_date'  => ['sometimes', 'date'],
            'end_date'    => ['nullable', 'date', 'after:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in'            => 'O tipo deve ser "income" ou "expense".',
            'amount.min'         => 'O valor deve ser maior que zero.',
            'category_id.exists' => 'Categoria inválida ou inacessível.',
            'frequency.in'       => 'A periodicidade deve ser "monthly" ou "yearly".',
            'end_date.after'     => 'A data de encerramento deve ser após a data de início.',
        ];
    }
}
