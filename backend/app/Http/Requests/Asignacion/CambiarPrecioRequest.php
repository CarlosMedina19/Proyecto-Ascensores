<?php

namespace App\Http\Requests\Asignacion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF-015: cambio del precio pactado con motivo obligatorio.
 */
class CambiarPrecioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'precio' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
