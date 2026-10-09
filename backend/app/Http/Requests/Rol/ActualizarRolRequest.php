<?php

namespace App\Http\Requests\Rol;

use App\Http\Requests\Concerns\MapearCamposApi;
use App\Models\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarRolRequest extends FormRequest
{
    use MapearCamposApi;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rol = $this->route('rol');
        $roleId = $rol instanceof Rol ? $rol->id : $rol;

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_-]*$/', Rule::unique('roles', 'nombre')->ignore($roleId)],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->mapApiFields([
            'nombre' => 'nombre',
            'descripcion' => 'descripcion',
        ], $this->all()));
    }
}
