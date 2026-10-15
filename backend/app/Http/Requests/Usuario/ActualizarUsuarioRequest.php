<?php

namespace App\Http\Requests\Usuario;

use App\Http\Requests\Concerns\MapearCamposApi;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarUsuarioRequest extends FormRequest
{
    use MapearCamposApi;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('usuario') instanceof Usuario
            ? $this->route('usuario')->id
            : $this->route('usuario');

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'correo' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('usuarios', 'correo')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8'],
            'rol_id' => ['sometimes', 'required', 'exists:roles,id'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->mapApiFields([
            'nombre' => 'nombre',
            'correo' => 'correo',
            'rol_id' => 'rol_id',
            'activo' => 'activo',
        ], $this->all()));
    }
}
