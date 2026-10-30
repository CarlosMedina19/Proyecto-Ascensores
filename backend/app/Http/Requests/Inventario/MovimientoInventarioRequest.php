<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF-041: entrada, salida, devolución o ajuste (el ajuste indica el stock contado). */
class MovimientoInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ajuste = $this->input('tipo') === 'ajuste';

        return [
            'tipo' => ['required', Rule::in(['entrada', 'salida', 'devolucion', 'ajuste'])],
            'cantidad' => [$ajuste ? 'prohibited' : 'required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'stock_contado' => [$ajuste ? 'required' : 'prohibited', 'numeric', 'min:0', 'max:9999999999.99'],
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['stock_contado' => 'stock contado'];
    }
}
