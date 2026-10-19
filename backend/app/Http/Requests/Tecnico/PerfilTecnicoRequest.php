<?php

namespace App\Http\Requests\Tecnico;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF-022: datos personales, especialidades y disponibilidad del técnico.
 * El documento es único y debe tener al menos una especialidad.
 */
class PerfilTecnicoRequest extends FormRequest
{
    public const TIPOS_DOCUMENTO = ['CC', 'CE', 'PA', 'PEP'];

    public function authorize(): bool
    {
        return true;
    }

    /** Usuario técnico que se edita (null al registrar uno nuevo). */
    protected function tecnicoActual(): ?Usuario
    {
        return $this->route('usuario');
    }

    public function rules(): array
    {
        $perfilId = $this->tecnicoActual()?->tecnico?->id;
        $requerido = $perfilId ? ['sometimes', 'required'] : ['required'];

        return [
            'tipo_documento' => [...$requerido, Rule::in(self::TIPOS_DOCUMENTO)],
            'numero_documento' => [...$requerido, 'string', 'regex:/^[A-Za-z0-9-]{5,20}$/', Rule::unique('tecnicos', 'numero_documento')->ignore($perfilId)],
            'telefono' => ['nullable', 'string', 'regex:/^[0-9 +()-]{7,20}$/'],
            'especialidades' => [...$requerido, 'array', 'min:1'],
            'especialidades.*' => ['integer', 'distinct', Rule::exists('especialidades', 'id')->where('activo', true)],
            'disponibilidad' => ['sometimes', Rule::in(['disponible', 'no_disponible'])],
            'motivo_no_disponible' => ['nullable', 'required_if:disponibilidad,no_disponible', 'string', 'max:255'],
            'no_disponible_hasta' => ['nullable', 'date', 'after_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_documento' => 'tipo de documento',
            'numero_documento' => 'número de documento',
            'telefono' => 'teléfono',
            'especialidades.*' => 'especialidad',
            'motivo_no_disponible' => 'motivo',
            'no_disponible_hasta' => 'fecha de regreso',
        ];
    }

    public function messages(): array
    {
        return [
            'especialidades.min' => 'El técnico debe tener al menos una especialidad.',
            'especialidades.required' => 'El técnico debe tener al menos una especialidad.',
            'numero_documento.unique' => 'Ya hay un técnico con ese número de documento.',
            'numero_documento.regex' => 'El documento solo admite letras, números y guiones (5 a 20 caracteres).',
            'motivo_no_disponible.required_if' => 'Indique por qué el técnico no está disponible.',
        ];
    }
}
