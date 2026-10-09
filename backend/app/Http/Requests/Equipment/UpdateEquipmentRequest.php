<?php

namespace App\Http\Requests\Equipment;

use App\Http\Requests\Concerns\MapsApiFields;
use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends FormRequest
{
    use MapsApiFields;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $equipmentId = $this->route('equipment') instanceof Equipment
            ? $this->route('equipment')->id
            : $this->route('equipment');

        return [
            // Datos generales del equipo
            'edificio_id' => ['sometimes', 'required', 'exists:edificios,id'],
            'codigo' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('equipos', 'codigo')->ignore($equipmentId)],
            'marca' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'numero_serie' => ['nullable', 'string', 'max:100'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'in:active,inactive,maintenance'],
            'fecha_instalacion' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string'],

            // Datos técnicos de ascensor
            'elevator' => ['nullable', 'array'],
            'elevator.marca' => ['nullable', 'string', 'max:100'],
            'elevator.modelo' => ['nullable', 'string', 'max:100'],
            'elevator.capacidad_kg' => ['nullable', 'integer', 'min:0'],
            'elevator.paradas' => ['nullable', 'integer', 'min:1'],
            'elevator.velocidad_mpm' => ['nullable', 'numeric', 'min:0'],
            'elevator.tipo_traccion' => ['nullable', 'string', 'max:100'],
            'elevator.motor' => ['nullable', 'string', 'max:100'],
            'elevator.controlador' => ['nullable', 'string', 'max:100'],
            'elevator.tipo_puerta' => ['nullable', 'string', 'max:100'],
            'elevator.especificaciones_tecnicas' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->mapApiFields([
            'building_id' => 'edificio_id',
            'code' => 'codigo',
            'brand' => 'marca',
            'model' => 'modelo',
            'serial_number' => 'numero_serie',
            'location' => 'ubicacion',
            'status' => 'estado',
            'installation_date' => 'fecha_instalacion',
            'observations' => 'observaciones',
        ], $this->all()));

        $elevator = $this->input('elevator');

        if (is_array($elevator)) {
            $this->merge([
                'elevator' => $this->mapApiFields([
                    'brand' => 'marca',
                    'model' => 'modelo',
                    'capacity_kg' => 'capacidad_kg',
                    'stops' => 'paradas',
                    'speed_mpm' => 'velocidad_mpm',
                    'drive_type' => 'tipo_traccion',
                    'controller' => 'controlador',
                    'door_type' => 'tipo_puerta',
                    'technical_specs' => 'especificaciones_tecnicas',
                    'installation_date' => 'fecha_instalacion',
                ], $elevator),
            ]);
        }
    }
}
