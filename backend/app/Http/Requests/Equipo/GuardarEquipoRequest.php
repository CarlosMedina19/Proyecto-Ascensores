<?php

namespace App\Http\Requests\Equipo;

use App\Models\Equipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF-007 (equipos) y RF-008 (ficha técnica del ascensor).
 */
class GuardarEquipoRequest extends FormRequest
{
    public const ESTADOS = ['activo', 'inactivo', 'mantenimiento'];

    public function authorize(): bool
    {
        return true;
    }

    protected function equipoActual(): ?Equipo
    {
        return null;
    }

    public function rules(): array
    {
        $actualizando = $this->equipoActual() !== null;
        $requerido = $actualizando ? ['sometimes', 'required'] : ['required'];

        return [
            'edificio_id' => [...$requerido, 'integer', Rule::exists('edificios', 'id')->whereNull('deleted_at')],
            'codigo' => [...$requerido, 'string', 'max:100', Rule::unique('equipos', 'codigo')->ignore($this->equipoActual()?->id)],
            'tipo' => ['nullable', Rule::in(['ascensor'])],
            'tecnico_id' => ['nullable', 'integer', Rule::exists('tecnicos', 'id')],
            'nombre' => ['nullable', 'string', 'max:100'],
            'marca' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'numero_serie' => ['nullable', 'string', 'max:100'],
            // La ubicación no se recibe: se toma del edificio (EquipoService).
            'estado' => ['nullable', Rule::in(self::ESTADOS)],
            'fecha_instalacion' => ['nullable', 'date', 'after_or_equal:1960-01-01', 'before_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:2000'],

            // Ficha técnica: capacidad, velocidad y paradas son obligatorias y mayores que cero.
            'ascensor' => [$actualizando ? 'sometimes' : 'required', 'array'],
            'ascensor.capacidad_kg' => [...$requerido, 'integer', 'min:100', 'max:5000'],
            'ascensor.capacidad_personas' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ascensor.velocidad_mpm' => [...$requerido, 'numeric', 'min:6', 'max:600'],
            'ascensor.paradas' => [...$requerido, 'integer', 'min:2', 'max:60'],
            'ascensor.tipo_traccion' => ['nullable', 'string', 'max:100'],
            'ascensor.motor' => ['nullable', 'string', 'max:100'],
            'ascensor.controlador' => ['nullable', 'string', 'max:100'],
            'ascensor.tipo_puerta' => ['nullable', 'string', 'max:100'],
            'ascensor.puertas_cabina' => ['nullable', 'integer', 'min:0', 'max:10'],
            'ascensor.puertas_piso' => ['nullable', 'integer', 'min:0', 'max:600'],
            'ascensor.operador_puertas' => ['nullable', 'string', 'max:100'],
            'ascensor.especificaciones_tecnicas' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'edificio_id' => 'edificio',
            'codigo' => 'código',
            'numero_serie' => 'número de serie',
            'fecha_instalacion' => 'fecha de instalación',
            'ascensor' => 'ficha técnica del ascensor',
            'ascensor.capacidad_kg' => 'capacidad (kg)',
            'ascensor.velocidad_mpm' => 'velocidad (m/min)',
            'ascensor.paradas' => 'número de paradas',
            'ascensor.tipo_traccion' => 'accionamiento',
            'ascensor.puertas_cabina' => 'puertas de cabina',
            'ascensor.puertas_piso' => 'puertas de piso',
            'ascensor.operador_puertas' => 'operador de puertas',
            'ascensor.especificaciones_tecnicas' => 'especificaciones técnicas',
        ];
    }
}
