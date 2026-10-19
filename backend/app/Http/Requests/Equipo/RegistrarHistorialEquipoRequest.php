<?php

namespace App\Http\Requests\Equipo;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarHistorialEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'evento' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'descripcion' => ['required', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['descripcion' => 'descripción'];
    }

    public function messages(): array
    {
        return [
            'evento.regex' => 'El evento solo admite minúsculas, números y guion bajo (ej. incidente, reparacion).',
        ];
    }
}
