<?php

namespace App\Http\Requests\Autenticacion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CambiarContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contrasena_actual' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:contrasena_actual', Password::min(8)->letters()->numbers()],
        ];
    }

    public function attributes(): array
    {
        return [
            'contrasena_actual' => 'contraseña actual',
            'password' => 'nueva contraseña',
        ];
    }
}
