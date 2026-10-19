<?php

namespace App\Http\Requests\Planilla;

use App\Models\OrdenTrabajo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF-038: datos opcionales del correctivo; si no se envían, se programa el siguiente día hábil.
 */
class CorrectivoDerivadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prioridad' => ['nullable', Rule::in(OrdenTrabajo::PRIORIDADES)],
            'fecha' => ['nullable', 'date', 'after_or_equal:today'],
            'hora' => ['nullable', 'date_format:H:i'],
            'duracion_estimada' => ['nullable', 'integer', 'min:15', 'max:720'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'tecnicos' => ['nullable', 'array'],
            'tecnicos.*' => ['integer', 'distinct', Rule::exists('tecnicos', 'id')],
        ];
    }
}
