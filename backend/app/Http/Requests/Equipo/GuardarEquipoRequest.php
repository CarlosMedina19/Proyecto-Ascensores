<?php

namespace App\Http\Requests\Equipo;

use App\Http\Requests\Concerns\MapearCamposApi;
use Illuminate\Foundation\Http\FormRequest;

class GuardarEquipoRequest extends FormRequest
{
    use MapearCamposApi;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Datos generales del equipo (RF-007)
            'edificio_id' => ['required', 'exists:edificios,id'],
            'codigo' => ['required', 'string', 'max:100', 'unique:equipos,codigo'],
            'tipo' => ['required', 'in:ascensor'],
            'marca' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'numero_serie' => ['nullable', 'string', 'max:100'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'in:activo,inactivo,mantenimiento'],
            'fecha_instalacion' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string'],

            // Datos técnicos específicos del ascensor (RF-008)
            'ascensor' => ['required', 'array'],
            'ascensor.marca' => ['nullable', 'string', 'max:100'],
            'ascensor.modelo' => ['nullable', 'string', 'max:100'],
            'ascensor.capacidad_kg' => ['nullable', 'integer', 'min:0'],
            'ascensor.paradas' => ['nullable', 'integer', 'min:1'],
            'ascensor.velocidad_mpm' => ['nullable', 'numeric', 'min:0'],
            'ascensor.tipo_traccion' => ['nullable', 'string', 'max:100'],
            'ascensor.motor' => ['nullable', 'string', 'max:100'],
            'ascensor.controlador' => ['nullable', 'string', 'max:100'],
            'ascensor.tipo_puerta' => ['nullable', 'string', 'max:100'],
            'ascensor.especificaciones_tecnicas' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->mapApiFields([
            'edificio_id' => 'edificio_id',
            'code' => 'codigo',
            'tipo' => 'tipo',
            'marca' => 'marca',
            'modelo' => 'modelo',
            'numero_serie' => 'numero_serie',
            'ubicacion' => 'ubicacion',
            'estado' => 'estado',
            'fecha_instalacion' => 'fecha_instalacion',
            'observaciones' => 'observaciones',
        ], $this->all()));

        $elevator = $this->input('ascensor', []);

        if (is_array($elevator)) {
            $this->merge([
                'ascensor' => $this->mapApiFields([
                    'marca' => 'marca',
                    'modelo' => 'modelo',
                    'capacidad_kg' => 'capacidad_kg',
                    'paradas' => 'paradas',
                    'velocidad_mpm' => 'velocidad_mpm',
                    'tipo_traccion' => 'tipo_traccion',
                    'controlador' => 'controlador',
                    'tipo_puerta' => 'tipo_puerta',
                    'especificaciones_tecnicas' => 'especificaciones_tecnicas',
                    'fecha_instalacion' => 'fecha_instalacion',
                ], $elevator),
            ]);
        }
    }
}
