<?php

namespace App\Http\Requests\Edificio;

use App\Models\Edificio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * RF-006: edificios por cliente con dirección, ciudad y al menos un contacto.
 */
class GuardarEdificioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function edificioActual(): ?Edificio
    {
        return null;
    }

    public function rules(): array
    {
        $requerido = $this->edificioActual() ? ['sometimes', 'required'] : ['required'];

        return [
            'cliente_id' => [...$requerido, 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
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
            'direccion' => 'dirección',
            'nombre_contacto' => 'nombre del contacto',
            'telefono_contacto' => 'teléfono del contacto',
            'correo_contacto' => 'correo del contacto',
            'codigo_postal' => 'código postal',
        ];
    }
}
