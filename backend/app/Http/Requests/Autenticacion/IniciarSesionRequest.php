<?php

namespace App\Http\Requests\Autenticacion;

use Illuminate\Foundation\Http\FormRequest;

class IniciarSesionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'correo' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'dispositivo' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['password' => 'contraseña'];
    }
}
