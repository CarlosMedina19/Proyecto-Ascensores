<?php

namespace App\Http\Requests\Equipo;

use App\Http\Requests\Concerns\MapearCamposApi;
use Illuminate\Foundation\Http\FormRequest;

class RegistrarHistorialEquipoRequest extends FormRequest
{
    use MapearCamposApi;

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
            'evento' => 'evento',
            'descripcion' => 'descripcion',
        ], $this->all()));
    }
}
