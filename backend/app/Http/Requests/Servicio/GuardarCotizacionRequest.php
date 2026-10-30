<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cotización de un correctivo con sus renglones (formulario y hoja Cotizaciones del Excel). */
class GuardarCotizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipo_id' => ['required', 'integer', Rule::exists('equipos', 'id')->whereNull('deleted_at')],
            'resultado_origen_id' => ['nullable', 'integer', Rule::exists('planilla_resultados', 'id')],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'fecha_emision' => ['nullable', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:today'],
            'iva_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'descuento' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.descripcion' => ['required', 'string', 'max:200'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'items.*.valor_unitario' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'equipo_id' => 'ascensor',
            'titulo' => 'título',
            'vigente_hasta' => 'vigencia',
            'iva_porcentaje' => 'IVA',
            'items' => 'renglones',
            'items.*.descripcion' => 'descripción del renglón',
            'items.*.cantidad' => 'cantidad',
            'items.*.valor_unitario' => 'valor unitario',
        ];
    }

    public function messages(): array
    {
        return ['items.required' => 'Agregue al menos un renglón con la descripción, la cantidad y el valor.'];
    }
}
