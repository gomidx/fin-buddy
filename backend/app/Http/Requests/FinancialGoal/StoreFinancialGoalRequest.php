<?php

namespace App\Http\Requests\FinancialGoal;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'target_amount'  => ['required', 'numeric', 'min:0.01'],
            'current_amount' => ['sometimes', 'numeric', 'min:0'],
            'target_date'    => ['sometimes', 'nullable', 'date', 'after:today'],
        ];
    }
}
