<?php

namespace App\Http\Requests\Cliente;

use App\Http\Requests\Concerns\MapearCamposApi;
use Illuminate\Foundation\Http\FormRequest;

class GuardarClienteRequest extends FormRequest
{
    use MapearCamposApi;

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
            'tipo' => 'tipo',
            'nombre' => 'nombre',
            'tipo_documento' => 'tipo_documento',
            'numero_documento' => 'numero_documento',
            'direccion' => 'direccion',
            'telefono' => 'telefono',
            'correo' => 'correo',
            'regimen_tributario' => 'regimen_tributario',
            'actividad_economica' => 'actividad_economica',
            'estado' => 'estado',
            'observaciones' => 'observaciones',
        ], $this->all()));
    }
}
