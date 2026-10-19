<?php

namespace App\Http\Requests\Cliente;

use Illuminate\Foundation\Http\FormRequest;

class GuardarContactoClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'required_without:correo', 'string', 'max:50'],
            'correo' => ['nullable', 'required_without:telefono', 'email', 'max:255'],
        ];
    }
}
