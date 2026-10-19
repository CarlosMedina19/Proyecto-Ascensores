<?php

namespace App\Http\Requests\Contrato;

use App\Models\Contrato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF-019: contrato con cliente, al menos un equipo, vigencia, valor y condiciones.
 */
class GuardarContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function contratoActual(): ?Contrato
    {
        return null;
    }

    public function rules(): array
    {
        $actual = $this->contratoActual();
        $requerido = $actual ? ['sometimes', 'required'] : ['required'];

        return [
            'cliente_id' => [$actual ? 'prohibited' : 'required', 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            'fecha_inicio' => [...$requerido, 'date'],
            'fecha_fin' => [...$requerido, 'date', 'after:fecha_inicio'],
            'valor' => [...$requerido, 'numeric', 'min:0', 'max:999999999999.99'],
            'servicios' => ['nullable', 'string', 'max:5000'],
            'condiciones' => ['nullable', 'string', 'max:10000'],
            'equipos' => [...$requerido, 'array', 'min:1'],
            'equipos.*' => ['integer', 'distinct', Rule::exists('equipos', 'id')->whereNull('deleted_at')],
        ];
    }

    public function attributes(): array
    {
        return [
            'cliente_id' => 'cliente',
            'fecha_inicio' => 'fecha inicial',
            'fecha_fin' => 'fecha final',
            'equipos' => 'ascensores',
            'equipos.*' => 'ascensor',
        ];
    }

    public function messages(): array
    {
        return [
            'equipos.min' => 'El contrato debe incluir al menos un ascensor.',
            'cliente_id.prohibited' => 'El cliente de un contrato no se puede cambiar.',
        ];
    }
}
