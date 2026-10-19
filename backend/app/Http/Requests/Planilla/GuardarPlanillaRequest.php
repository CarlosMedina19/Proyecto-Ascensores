<?php

namespace App\Http\Requests\Planilla;

use App\Models\PlanillaResultado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF-037: cada ítem con un resultado (INS, EST o NA) y un estado (B, R o M);
 * R o M exigen observación.
 */
class GuardarPlanillaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resultados' => ['nullable', 'array', 'max:200'],
            'resultados.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('checklist_items', 'id')],
            'resultados.*.resultado' => ['required', Rule::in(PlanillaResultado::RESULTADOS)],
            'resultados.*.estado' => ['required', Rule::in(PlanillaResultado::ESTADOS)],
            'resultados.*.observacion' => ['nullable', 'required_unless:resultados.*.estado,B', 'string', 'max:1000'],
            'recomendaciones' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'resultados.*.item_id' => 'ítem',
            'resultados.*.resultado' => 'resultado',
            'resultados.*.estado' => 'estado',
            'resultados.*.observacion' => 'observación',
        ];
    }

    public function messages(): array
    {
        return [
            'resultados.*.resultado.in' => 'El resultado debe ser INS, EST o N.A.',
            'resultados.*.estado.in' => 'El estado debe ser B, R o M.',
            'resultados.*.observacion.required_unless' => 'Un ítem en estado Regular o Malo exige una observación.',
        ];
    }
}
