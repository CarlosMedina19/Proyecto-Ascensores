<?php

namespace App\Http\Requests\Contrato;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF-020: el seguimiento registra el resultado. "Renovado" se registra al renovar.
 */
class SeguimientoRenovacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resultado' => ['required', Rule::in(['contactado', 'no_renovado'])],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['resultado.in' => 'El resultado debe ser «contactado» o «no renovado». Para renovar use «Renovar contrato».'];
    }
}
