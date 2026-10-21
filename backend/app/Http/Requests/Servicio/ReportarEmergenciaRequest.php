<?php

namespace App\Http\Requests\Servicio;

use App\Models\Emergencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Reporte de una emergencia desde el panel, la app o el QR de la cabina (sin sesión). */
class ReportarEmergenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $publico = $this->user() === null;

        return [
            'equipo_id' => [$publico ? 'prohibited' : 'required', 'integer', Rule::exists('equipos', 'id')->whereNull('deleted_at')],
            'tipo' => ['required', Rule::in(array_keys(Emergencia::TIPOS))],
            'personas_atrapadas' => ['sometimes', 'boolean'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'reportante_nombre' => [$publico ? 'required' : 'nullable', 'string', 'max:120'],
            'reportante_telefono' => [$publico ? 'required' : 'nullable', 'string', 'max:30', 'regex:/^[0-9 +()-]{7,30}$/'],
            'tecnico_id' => [$publico ? 'prohibited' : 'nullable', 'integer', Rule::exists('tecnicos', 'id')],
        ];
    }

    public function attributes(): array
    {
        return [
            'equipo_id' => 'ascensor',
            'tipo' => 'tipo de emergencia',
            'reportante_nombre' => 'nombre de quien reporta',
            'reportante_telefono' => 'teléfono de contacto',
            'tecnico_id' => 'técnico',
        ];
    }

    public function messages(): array
    {
        return [
            'reportante_telefono.regex' => 'Escriba un teléfono válido (solo números, espacios, +, paréntesis o guiones).',
        ];
    }
}
