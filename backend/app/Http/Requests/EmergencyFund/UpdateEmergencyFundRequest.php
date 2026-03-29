<?php

namespace App\Http\Requests\EmergencyFund;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmergencyFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_months' => ['required', 'integer', 'min:1', 'max:60'],
            'target_amount'  => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_months.required' => 'O número de meses é obrigatório.',
            'target_months.integer'  => 'O número de meses deve ser um inteiro.',
            'target_months.min'      => 'O número de meses deve ser pelo menos 1.',
            'target_months.max'      => 'O número de meses não pode exceder 60.',
            'target_amount.numeric'  => 'O valor alvo deve ser numérico.',
            'target_amount.min'      => 'O valor alvo deve ser maior que zero.',
        ];
    }
}
