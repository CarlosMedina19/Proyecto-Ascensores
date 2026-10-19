<?php

namespace App\Http\Requests\Orden;

use App\Models\OrdenTrabajo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF-025: toda orden requiere cliente, edificio, ascensor, servicio, fecha y hora.
 * Si se crea desde una visita programada, esos datos se toman de la visita.
 */
class GuardarOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $desdeVisita = $this->filled('mantenimiento_id');
        $requerido = $desdeVisita ? ['nullable'] : ['required'];

        return [
            'mantenimiento_id' => ['nullable', 'integer', Rule::exists('mantenimientos_programados', 'id')],
            'servicio' => [...$requerido, Rule::in(OrdenTrabajo::SERVICIOS)],
            'cliente_id' => [...$requerido, 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            'edificio_id' => [...$requerido, 'integer', Rule::exists('edificios', 'id')->whereNull('deleted_at')],
            'equipo_id' => [...$requerido, 'integer', Rule::exists('equipos', 'id')->whereNull('deleted_at')],
            'fecha' => [...$requerido, 'date'],
            'hora' => [...$requerido, 'date_format:H:i'],
            'duracion_estimada' => ['nullable', 'integer', 'min:15', 'max:720'],
            'prioridad' => ['nullable', Rule::in(OrdenTrabajo::PRIORIDADES)],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'tecnicos' => ['nullable', 'array'],
            'tecnicos.*' => ['integer', 'distinct', Rule::exists('tecnicos', 'id')],
        ];
    }

    public function attributes(): array
    {
        return [
            'cliente_id' => 'cliente',
            'edificio_id' => 'edificio',
            'equipo_id' => 'ascensor',
            'duracion_estimada' => 'duración estimada',
            'descripcion' => 'descripción',
            'tecnicos.*' => 'técnico',
        ];
    }
}
