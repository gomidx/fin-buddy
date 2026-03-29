<?php

namespace App\Http\Requests\RecurringTransaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringTransactionRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:255'],
            'frequency'   => ['required', Rule::in(['monthly', 'yearly'])],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['nullable', 'date', 'after:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'        => 'O tipo é obrigatório.',
            'type.in'              => 'O tipo deve ser "income" ou "expense".',
            'amount.required'      => 'O valor é obrigatório.',
            'amount.min'           => 'O valor deve ser maior que zero.',
            'category_id.required' => 'A categoria é obrigatória.',
            'category_id.exists'   => 'Categoria inválida ou inacessível.',
            'description.required' => 'A descrição é obrigatória.',
            'frequency.required'   => 'A periodicidade é obrigatória.',
            'frequency.in'         => 'A periodicidade deve ser "monthly" ou "yearly".',
            'start_date.required'  => 'A data de início é obrigatória.',
            'start_date.date'      => 'Informe uma data de início válida.',
            'end_date.after'       => 'A data de encerramento deve ser após a data de início.',
        ];
    }
}
