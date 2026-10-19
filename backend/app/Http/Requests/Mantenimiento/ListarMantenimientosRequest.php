<?php

namespace App\Http\Requests\Mantenimiento;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Consulta del calendario: un rango de fechas de máximo 100 días.
 */
class ListarMantenimientosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'equipo_id' => ['nullable', 'integer'],
            'cliente_id' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::in(['programado', 'reprogramado', 'ejecutado', 'cancelado'])],
            'sin_orden' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validador) {
                if ($validador->errors()->isNotEmpty()) {
                    return;
                }
                $dias = CarbonImmutable::parse($this->input('desde'))->diffInDays(CarbonImmutable::parse($this->input('hasta')));
                if ($dias > 100) {
                    $validador->errors()->add('hasta', 'El rango de fechas no puede superar 100 días.');
                }
            },
        ];
    }
}
