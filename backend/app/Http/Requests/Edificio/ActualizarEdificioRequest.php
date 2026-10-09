<?php

namespace App\Http\Requests\Edificio;

use App\Http\Requests\Concerns\MapearCamposApi;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarEdificioRequest extends FormRequest
{
    use MapearCamposApi;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['sometimes', 'required', 'exists:clientes,id'],
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'direccion' => ['sometimes', 'required', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'codigo_postal' => ['nullable', 'string', 'max:20'],
            'pisos' => ['nullable', 'integer', 'min:1'],
            'observaciones' => ['nullable', 'string'],
            'nombre_contacto' => ['nullable', 'string', 'max:255'],
            'telefono_contacto' => ['nullable', 'string', 'max:50'],
            'correo_contacto' => ['nullable', 'email', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->mapApiFields([
            'cliente_id' => 'cliente_id',
            'nombre' => 'nombre',
            'direccion' => 'direccion',
            'ciudad' => 'ciudad',
            'departamento' => 'departamento',
            'codigo_postal' => 'codigo_postal',
            'pisos' => 'pisos',
            'observaciones' => 'observaciones',
            'nombre_contacto' => 'nombre_contacto',
            'telefono_contacto' => 'telefono_contacto',
            'correo_contacto' => 'correo_contacto',
        ], $this->all()));
    }
}
