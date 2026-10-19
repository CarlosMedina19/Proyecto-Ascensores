<?php

namespace App\Http\Requests\Plan;

use App\Models\PlanMantenimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF-010 y RF-012: plan con nombre único y un precio (mayor que cero) por periodicidad.
 */
class GuardarPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function planActual(): ?PlanMantenimiento
    {
        return null;
    }

    public function rules(): array
    {
        $actual = $this->planActual();
        $requerido = $actual ? ['sometimes', 'required'] : ['required'];

        return [
            'nombre' => [...$requerido, 'string', 'max:150', Rule::unique('planes_mantenimiento', 'nombre')->ignore($actual?->id)],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'servicios_incluidos' => ['nullable', 'array', 'max:50'],
            'servicios_incluidos.*' => ['string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
            // Los precios iniciales solo se envían al crear; después se cambian con motivo (RF-015).
            'precios' => [$actual ? 'prohibited' : 'required', 'array', 'min:1'],
            'precios.*.periodicidad_id' => ['required', 'integer', 'distinct', Rule::exists('periodicidades', 'id')->where('activo', true)],
            'precios.*.precio' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
        ];
    }

    public function attributes(): array
    {
        return [
            'servicios_incluidos' => 'servicios incluidos',
            'precios.*.periodicidad_id' => 'periodicidad',
            'precios.*.precio' => 'precio',
        ];
    }

    public function messages(): array
    {
        return [
            'precios.prohibited' => 'Los precios se cambian desde «Cambiar precios», indicando el motivo.',
        ];
    }
}
