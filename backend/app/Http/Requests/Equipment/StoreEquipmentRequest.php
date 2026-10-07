<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Datos generales del equipo (RF-007)
            'building_id' => ['required', 'exists:buildings,id'],
            'code' => ['required', 'string', 'max:100', 'unique:equipment,code'],
            'type' => ['required', 'in:elevator,electric_door'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,maintenance'],
            'installation_date' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],

            // Datos técnicos específicos si es ascensor (RF-008)
            'elevator' => ['required_if:type,elevator', 'array'],
            'elevator.brand' => ['nullable', 'string', 'max:100'],
            'elevator.model' => ['nullable', 'string', 'max:100'],
            'elevator.capacity_kg' => ['nullable', 'integer', 'min:0'],
            'elevator.stops' => ['nullable', 'integer', 'min:1'],
            'elevator.speed_mpm' => ['nullable', 'numeric', 'min:0'],
            'elevator.drive_type' => ['nullable', 'string', 'max:100'],
            'elevator.motor' => ['nullable', 'string', 'max:100'],
            'elevator.controller' => ['nullable', 'string', 'max:100'],
            'elevator.door_type' => ['nullable', 'string', 'max:100'],
            'elevator.technical_specs' => ['nullable', 'string'],

            // Datos técnicos específicos si es puerta eléctrica (RF-009)
            'electric_door' => ['required_if:type,electric_door', 'array'],
            'electric_door.brand' => ['nullable', 'string', 'max:100'],
            'electric_door.model' => ['nullable', 'string', 'max:100'],
            'electric_door.door_type' => ['nullable', 'string', 'max:100'],
            'electric_door.opening_type' => ['nullable', 'in:simple,doble'],
            'electric_door.access_type' => ['nullable', 'in:entrada,salida,ambos'],
            'electric_door.serial_number' => ['nullable', 'string', 'max:100'],
            'electric_door.technical_specs' => ['nullable', 'string'],
        ];
    }
}
