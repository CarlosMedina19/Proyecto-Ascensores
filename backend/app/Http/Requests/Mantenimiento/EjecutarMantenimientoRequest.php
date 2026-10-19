<?php

namespace App\Http\Requests\Mantenimiento;

use Illuminate\Foundation\Http\FormRequest;

class EjecutarMantenimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_ejecucion' => ['required', 'date', 'before_or_equal:today'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['fecha_ejecucion' => 'fecha de ejecución', 'observacion' => 'observación'];
    }
}
