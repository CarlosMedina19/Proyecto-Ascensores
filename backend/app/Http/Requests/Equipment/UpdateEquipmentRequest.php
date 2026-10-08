<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $equipmentId = $this->route('equipment') instanceof \App\Models\Equipment 
            ? $this->route('equipment')->id 
            : $this->route('equipment');

        return [
            // Datos generales del equipo
            'building_id' => ['sometimes', 'required', 'exists:buildings,id'],
            'code' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('equipment')->ignore($equipmentId)],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,maintenance'],
            'installation_date' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],

            // Datos técnicos de ascensor
            'elevator' => ['nullable', 'array'],
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
