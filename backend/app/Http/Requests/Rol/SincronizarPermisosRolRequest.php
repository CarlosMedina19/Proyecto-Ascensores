<?php

namespace App\Http\Requests\Rol;

use App\Models\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SincronizarPermisosRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permisos' => ['present', 'array', 'max:200'],
            'permisos.*' => ['required', 'string', 'distinct', 'exists:permisos,nombre'],
        ];
    }

    /**
     * RF-002/RF-003/RF-004: hay permisos que solo puede tener el administrador.
     */
    public function after(): array
    {
        return [
            function (Validator $validador) {
                /** @var Rol $rol */
                $rol = $this->route('rol');
                $exclusivos = array_intersect(
                    (array) $this->input('permisos', []),
                    config('sigma.permisos_exclusivos_administrador')
                );

                if ($rol->nombre !== 'administrador' && $exclusivos !== []) {
                    $validador->errors()->add(
                        'permisos',
                        'Estos permisos son exclusivos del administrador: '.implode(', ', $exclusivos).'.'
                    );
                }
            },
        ];
    }
}
