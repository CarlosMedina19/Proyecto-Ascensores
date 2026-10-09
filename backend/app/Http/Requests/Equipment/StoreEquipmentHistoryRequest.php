<?php

namespace App\Http\Requests\Equipment;

use App\Http\Requests\Concerns\MapsApiFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentHistoryRequest extends FormRequest
{
    use MapsApiFields;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'evento' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->mapApiFields([
            'event' => 'evento',
            'description' => 'descripcion',
        ], $this->all()));
    }
}
