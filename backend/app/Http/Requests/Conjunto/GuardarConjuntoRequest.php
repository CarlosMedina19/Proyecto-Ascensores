<?php

namespace App\Http\Requests\Conjunto;

use App\Http\Requests\Concerns\ValidaUbicacion;
use App\Models\Conjunto;
use App\Models\Edificio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Conjunto, institución o residencia: cliente, tipo, nombre, ubicación y contacto.
 */
class GuardarConjuntoRequest extends FormRequest
{
    use ValidaUbicacion;

    public function authorize(): bool
    {
        return true;
    }

    protected function conjuntoActual(): ?Conjunto
    {
        $conjunto = $this->route('conjunto');

        return $conjunto instanceof Conjunto ? $conjunto : null;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarUbicacion();
    }

    public function rules(): array
    {
        $requerido = $this->conjuntoActual() ? ['sometimes', 'required'] : ['required'];

        return [
            'cliente_id' => [...$requerido, 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            'tipo' => [...$requerido, Rule::in(array_keys(Edificio::TIPOS))],
            'nombre' => [...$requerido, 'string', 'max:255'],
            'direccion' => [...$requerido, 'string', 'max:255'],
            'ciudad' => [...$requerido, 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'codigo_postal' => ['nullable', 'string', 'max:20'],
            'nombre_contacto' => ['nullable', 'string', 'max:255'],
            'telefono_contacto' => ['nullable', 'string', 'max:50'],
            'correo_contacto' => ['nullable', 'email', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validador) => $this->validarUbicacion($validador)];
    }

    public function attributes(): array
    {
        return [
            'cliente_id' => 'cliente',
            'direccion' => 'dirección',
            'codigo_postal' => 'código postal',
            'nombre_contacto' => 'nombre del contacto',
            'telefono_contacto' => 'teléfono del contacto',
            'correo_contacto' => 'correo del contacto',
        ];
    }
}
