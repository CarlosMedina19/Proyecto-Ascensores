<?php

namespace App\Http\Requests\Orden;

use App\Models\OrdenTrabajo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación de las acciones sobre una orden (según la ruta): asignar, reprogramar,
 * prioridad y cambio de estado.
 */
class AccionOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'asignar' => [
                'tecnicos' => ['required', 'array', 'min:1'],
                'tecnicos.*' => ['integer', 'distinct', Rule::exists('tecnicos', 'id')],
            ],
            'reprogramar' => [
                'fecha' => ['required', 'date', 'after_or_equal:today'],
                'hora' => ['required', 'date_format:H:i'],
                'duracion_estimada' => ['nullable', 'integer', 'min:15', 'max:720'],
                'motivo' => ['required', 'string', 'min:5', 'max:1000'],
            ],
            'prioridad' => [
                'prioridad' => ['required', Rule::in(OrdenTrabajo::PRIORIDADES)],
                'motivo' => ['nullable', 'string', 'max:1000'],
            ],
            'estado' => [
                'estado' => ['required', Rule::in(['en_camino', 'en_proceso', 'pendiente', 'finalizada'])],
                'motivo' => ['nullable', 'required_if:estado,pendiente', 'string', 'min:5', 'max:1000'],
                'informe' => ['nullable', 'required_if:estado,finalizada', 'string', 'min:10', 'max:10000'],
                'observaciones' => ['nullable', 'string', 'max:5000'],
            ],
            default => [],
        };
    }

    public function attributes(): array
    {
        return [
            'tecnicos' => 'técnicos',
            'tecnicos.*' => 'técnico',
            'duracion_estimada' => 'duración estimada',
        ];
    }

    public function messages(): array
    {
        return [
            'tecnicos.required' => 'La orden debe tener al menos un técnico.',
            'tecnicos.min' => 'La orden debe tener al menos un técnico.',
            'motivo.required_if' => 'Indique por qué la orden queda pendiente.',
            'informe.required_if' => 'Escriba el informe del trabajo realizado para finalizar la orden.',
        ];
    }
}
