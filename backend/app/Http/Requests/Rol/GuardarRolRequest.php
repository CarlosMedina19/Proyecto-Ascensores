<?php

namespace App\Http\Requests\Rol;

use App\Http\Requests\Concerns\MapearCamposApi;
use Illuminate\Foundation\Http\FormRequest;

class GuardarRolRequest extends FormRequest
{
    use MapearCamposApi;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_-]*$/', 'unique:roles,nombre'],
            'descripcion' => ['nullable', 'string', 'max:255'],
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
