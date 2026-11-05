<?php

namespace App\Http\Requests\Edificio;

use App\Http\Requests\Concerns\ValidaUbicacion;
use App\Models\Conjunto;
use App\Models\Edificio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * RF-006: edificios por cliente con dirección, ciudad y al menos un contacto.
 * Dentro de un conjunto, el edificio hereda su cliente y su tipo; lo que no se envíe
 * de ubicación y contacto se toma del conjunto.
 */
class GuardarEdificioRequest extends FormRequest
{
    use ValidaUbicacion;

    public function authorize(): bool
    {
        return true;
    }

    protected function edificioActual(): ?Edificio
    {
        return null;
    }

    protected function prepareForValidation(): void
    {
        $conjunto = $this->filled('conjunto_id') ? Conjunto::find($this->input('conjunto_id')) : null;
        if ($conjunto) {
            $heredado = ['cliente_id' => $conjunto->cliente_id, 'tipo' => $conjunto->tipo];
            if (! $this->edificioActual()) {
                foreach (['direccion', 'ciudad', 'departamento', 'codigo_postal', 'nombre_contacto', 'telefono_contacto', 'correo_contacto'] as $campo) {
                    if (blank($this->input($campo))) {
                        $heredado[$campo] = $conjunto->{$campo};
                    }
                }
            }
            $this->merge($heredado);
        }
        $this->normalizarUbicacion();
    }

    public function rules(): array
    {
        $requerido = $this->edificioActual() ? ['sometimes', 'required'] : ['required'];

        return [
            'cliente_id' => [...$requerido, 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            'conjunto_id' => ['nullable', 'integer', Rule::exists('conjuntos', 'id')->whereNull('deleted_at')],
            'tipo' => [...$requerido, Rule::in(array_keys(Edificio::TIPOS))],
            'nombre' => [...$requerido, 'string', 'max:255'],
            'direccion' => [...$requerido, 'string', 'max:255'],
            'ciudad' => [...$requerido, 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'codigo_postal' => ['nullable', 'string', 'max:20'],
            'pisos' => ['nullable', 'integer', 'min:1', 'max:300'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'nombre_contacto' => [...$requerido, 'string', 'max:255'],
            'telefono_contacto' => ['nullable', 'string', 'max:50'],
            'correo_contacto' => ['nullable', 'email', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * El contacto del edificio debe tener al menos teléfono o correo.
     */
    public function after(): array
    {
        return [
            fn (Validator $validador) => $this->validarUbicacion($validador),
            function (Validator $validador) {
                $actual = $this->edificioActual();
                $telefono = $this->exists('telefono_contacto') ? $this->input('telefono_contacto') : $actual?->telefono_contacto;
                $correo = $this->exists('correo_contacto') ? $this->input('correo_contacto') : $actual?->correo_contacto;

                if (blank($telefono) && blank($correo)) {
                    $validador->errors()->add('telefono_contacto', 'Registre el teléfono o el correo del contacto del edificio.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'cliente_id' => 'cliente',
            'conjunto_id' => 'conjunto',
            'direccion' => 'dirección',
            'nombre_contacto' => 'nombre del contacto',
            'telefono_contacto' => 'teléfono del contacto',
            'correo_contacto' => 'correo del contacto',
            'codigo_postal' => 'código postal',
        ];
    }
}
