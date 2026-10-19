<?php

namespace App\Http\Requests\Plan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF-015: todo cambio de precio exige el motivo.
 */
class ActualizarPreciosPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'precios' => ['required', 'array', 'min:1'],
            'precios.*.periodicidad_id' => ['required', 'integer', 'distinct', Rule::exists('periodicidades', 'id')],
            'precios.*.precio' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'precios.*.periodicidad_id' => 'periodicidad',
            'precios.*.precio' => 'precio',
        ];
    }
}
