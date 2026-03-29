<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['sometimes', 'string', 'max:255'],
            'email'        => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'password'     => ['sometimes', 'string', 'min:8', 'confirmed'],
            'currency'     => ['sometimes', 'string', 'size:3'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email'        => 'Informe um e-mail válido.',
            'email.unique'       => 'Este e-mail já está em uso.',
            'password.min'       => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'As senhas não conferem.',
            'currency.size'      => 'A moeda deve ter 3 caracteres (ex: BRL).',
        ];
    }
}
