<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Concerns\MapsApiFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    use MapsApiFields;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['nullable', 'in:natural,juridica'],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo_documento' => ['nullable', 'string', 'max:50'],
            'numero_documento' => ['nullable', 'string', 'max:50'],
            'nit' => ['nullable', 'string', 'max:50', 'unique:clientes,nit'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:255'],
            'regimen_tributario' => ['nullable', 'string', 'max:100'],
            'actividad_economica' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->mapApiFields([
            'type' => 'tipo',
            'name' => 'nombre',
            'document_type' => 'tipo_documento',
            'document_number' => 'numero_documento',
            'address' => 'direccion',
            'phone' => 'telefono',
            'email' => 'correo',
            'tax_regime' => 'regimen_tributario',
            'economic_activity' => 'actividad_economica',
            'status' => 'estado',
            'observations' => 'observaciones',
        ], $this->all()));
    }
}
