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
            // Datos generales del ascensor (RF-007)
            'building_id' => ['required', 'exists:buildings,id'],
            'code' => ['required', 'string', 'max:100', 'unique:equipment,code'],
            'type' => ['required', 'in:elevator'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,maintenance'],
            'installation_date' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],

            // Datos técnicos específicos del ascensor (RF-008)
            'elevator' => ['required', 'array'],
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
        ];
    }
}
