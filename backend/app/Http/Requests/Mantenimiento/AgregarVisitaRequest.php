<?php

namespace App\Http\Requests\Mantenimiento;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Visita adicional de mantenimiento en el día elegido en el calendario. */
class AgregarVisitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipo_id' => ['required', 'integer', Rule::exists('equipos', 'id')->whereNull('deleted_at')],
            'fecha' => ['required', 'date'],
            'hora' => ['nullable', 'date_format:H:i', 'after_or_equal:06:00', 'before_or_equal:18:00'],
            'motivo' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['equipo_id' => 'ascensor'];
    }
}
