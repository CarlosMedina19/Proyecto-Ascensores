<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF-042: repuesto usado en una orden. */
class RepuestoOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'repuesto_id' => ['required', 'integer', Rule::exists('repuestos', 'id')],
            'cantidad' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ];
    }

    public function attributes(): array
    {
        return ['repuesto_id' => 'repuesto'];
    }
}
