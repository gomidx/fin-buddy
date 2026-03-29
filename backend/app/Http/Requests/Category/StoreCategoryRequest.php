<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories')->where(function ($query) {
                    return $query->where('user_id', $this->user()->id);
                }),
            ],
            'type' => ['required', Rule::in(['income', 'expense'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome da categoria é obrigatório.',
            'name.unique'   => 'Você já possui uma categoria com este nome.',
            'type.required' => 'O tipo da categoria é obrigatório.',
            'type.in'       => 'O tipo deve ser "income" ou "expense".',
        ];
    }
}
