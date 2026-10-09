<?php

namespace App\Http\Requests\Building;

use App\Http\Requests\Concerns\MapsApiFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBuildingRequest extends FormRequest
{
    use MapsApiFields;

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
            'client_id' => 'cliente_id',
            'name' => 'nombre',
            'address' => 'direccion',
            'city' => 'ciudad',
            'department' => 'departamento',
            'postal_code' => 'codigo_postal',
            'floors' => 'pisos',
            'observations' => 'observaciones',
            'contact_name' => 'nombre_contacto',
            'contact_phone' => 'telefono_contacto',
            'contact_email' => 'correo_contacto',
        ], $this->all()));
    }
}
