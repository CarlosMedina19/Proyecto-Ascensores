<?php

namespace App\Http\Requests\Autenticacion;

use Illuminate\Foundation\Http\FormRequest;

class RecuperarContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'correo' => ['required', 'email', 'max:255'],
        ];
    }
}
