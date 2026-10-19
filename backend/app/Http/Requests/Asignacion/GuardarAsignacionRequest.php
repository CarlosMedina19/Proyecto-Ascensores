<?php

namespace App\Http\Requests\Asignacion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * RF-013 y RF-014: asignación de un plan a un ascensor con su vigencia.
 */
class GuardarAsignacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipo_id' => ['required', 'integer', Rule::exists('equipos', 'id')->whereNull('deleted_at')],
            'plan_id' => ['required', 'integer', Rule::exists('planes_mantenimiento', 'id')->whereNull('deleted_at')],
            'periodicidad_id' => ['required', 'integer', Rule::exists('periodicidades', 'id')],
            'contrato_id' => ['nullable', 'integer', Rule::exists('contratos', 'id')],
            'precio' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
            'aplica_iva' => ['nullable', 'boolean'],
            'porcentaje_iva' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after:fecha_inicio'],
            'hora_preferida' => ['nullable', 'date_format:H:i', 'after_or_equal:06:00', 'before_or_equal:18:00'],
        ];
    }

    /**
     * RF-015: un precio distinto al del catálogo solo lo fija el contador o el administrador.
     */
    public function after(): array
    {
        return [
            function (Validator $validador) {
                if ($this->filled('precio') && ! $this->user()?->tienePermiso('precios.editar')) {
                    $validador->errors()->add('precio', 'Solo el contador o el administrador pueden fijar un precio distinto al del catálogo.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'equipo_id' => 'ascensor',
            'plan_id' => 'plan',
            'periodicidad_id' => 'periodicidad',
            'contrato_id' => 'contrato',
            'porcentaje_iva' => 'porcentaje de IVA',
            'fecha_inicio' => 'fecha inicial',
            'fecha_fin' => 'fecha final',
        ];
    }
}
