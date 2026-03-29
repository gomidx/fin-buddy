<?php

namespace App\Http\Requests\EmergencyFund;

use Illuminate\Foundation\Http\FormRequest;

class DepositEmergencyFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'date'        => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'O valor é obrigatório.',
            'amount.numeric'  => 'O valor deve ser numérico.',
            'amount.min'      => 'O valor deve ser maior que zero.',
            'date.required'   => 'A data é obrigatória.',
            'date.date'       => 'Informe uma data válida.',
        ];
    }
}
