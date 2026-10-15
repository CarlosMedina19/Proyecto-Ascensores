<?php

namespace App\Http\Requests\Rol;

use Illuminate\Foundation\Http\FormRequest;

class SincronizarPermisosRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permisos' => ['present', 'array', 'max:100'],
            'permissions.*' => ['required', 'string', 'distinct', 'exists:permisos,nombre'],
        ];
    }
}
