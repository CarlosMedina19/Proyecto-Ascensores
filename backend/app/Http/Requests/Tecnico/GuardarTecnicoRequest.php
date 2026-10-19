<?php

namespace App\Http\Requests\Tecnico;

use App\Models\Usuario;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * RF-022: registra el técnico con su cuenta de usuario.
 */
class GuardarTecnicoRequest extends PerfilTecnicoRequest
{
    protected function tecnicoActual(): ?Usuario
    {
        return null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('correo'))) {
            $this->merge(['correo' => strtolower(trim($this->input('correo')))]);
        }
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email', 'max:255', Rule::unique('usuarios', 'correo')],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'activo' => ['nullable', 'boolean'],
            ...parent::rules(),
        ];
    }
}
