<?php

namespace App\Http\Requests\Cliente;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * RF-005: clientes personas naturales o jurídicas con documento o NIT único.
 */
class GuardarClienteRequest extends FormRequest
{
    public const TIPOS_DOCUMENTO = ['CC', 'CE', 'TI', 'PA', 'PEP', 'NIT'];

    public function authorize(): bool
    {
        return true;
    }

    protected function clienteActual(): ?Cliente
    {
        return null;
    }

    public function rules(): array
    {
        $ignorar = $this->clienteActual()?->id;
        $requerido = $this->clienteActual() ? ['sometimes', 'required'] : ['required'];

        return [
            'codigo' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('clientes', 'codigo')->ignore($ignorar)],
            'tipo' => [...$requerido, Rule::in(['natural', 'juridica'])],
            'nombre' => [...$requerido, 'string', 'max:255'],
            'tipo_documento' => ['nullable', Rule::in(self::TIPOS_DOCUMENTO)],
            'numero_documento' => ['nullable', 'string', 'max:50', Rule::unique('clientes', 'numero_documento')->ignore($ignorar)],
            'nit' => ['nullable', 'string', 'max:50', 'regex:/^[0-9]{5,15}(-[0-9])?$/', Rule::unique('clientes', 'nit')->ignore($ignorar)],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:255'],
            'regimen_tributario' => ['nullable', 'string', 'max:100'],
            'actividad_economica' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Cada cliente debe tener NIT (jurídica) o número de documento (natural).
     */
    public function after(): array
    {
        return [
            function (Validator $validador) {
                $actual = $this->clienteActual();
                $tipo = $this->input('tipo', $actual?->tipo);
                $nit = $this->exists('nit') ? $this->input('nit') : $actual?->nit;
                $documento = $this->exists('numero_documento') ? $this->input('numero_documento') : $actual?->numero_documento;

                if ($tipo === 'juridica' && blank($nit)) {
                    $validador->errors()->add('nit', 'El NIT es obligatorio para personas jurídicas.');
                }

                if ($tipo === 'natural' && blank($documento)) {
                    $validador->errors()->add('numero_documento', 'El número de documento es obligatorio para personas naturales.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'numero_documento' => 'número de documento',
            'tipo_documento' => 'tipo de documento',
            'nit' => 'NIT',
            'regimen_tributario' => 'régimen tributario',
            'actividad_economica' => 'actividad económica',
        ];
    }

    public function messages(): array
    {
        return [
            'nit.regex' => 'El NIT debe tener solo números y, opcionalmente, el dígito de verificación (ej. 900123456-1).',
        ];
    }
}
