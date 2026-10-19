<?php

namespace App\Http\Requests\Mantenimiento;

use Illuminate\Foundation\Http\FormRequest;

class ReprogramarMantenimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            // Jornada de visitas: de 06:00 a 18:00.
            'hora' => ['nullable', 'date_format:H:i', 'after_or_equal:06:00', 'before_or_equal:18:00'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
