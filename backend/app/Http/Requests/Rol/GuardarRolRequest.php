<?php

namespace App\Http\Requests\Rol;

use App\Models\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function rolActual(): ?Rol
    {
        return null;
    }

    public function rules(): array
    {
        $requerido = $this->rolActual() ? ['sometimes', 'required'] : ['required'];

        return [
            'nombre' => [...$requerido, 'string', 'max:100', 'regex:/^[a-z][a-z0-9_-]*$/', Rule::unique('roles', 'nombre')->ignore($this->rolActual()?->id)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.regex' => 'El nombre del rol solo admite minúsculas, números, guion y guion bajo, y debe iniciar con una letra.',
        ];
    }
}
