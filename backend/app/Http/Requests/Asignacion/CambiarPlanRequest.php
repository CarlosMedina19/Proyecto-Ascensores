<?php

namespace App\Http\Requests\Asignacion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF-016: cambio de plan o de periodicidad con motivo obligatorio.
 */
class CambiarPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['nullable', 'required_without:periodicidad_id', 'integer', Rule::exists('planes_mantenimiento', 'id')->whereNull('deleted_at')],
            'periodicidad_id' => ['nullable', 'required_without:plan_id', 'integer', Rule::exists('periodicidades', 'id')],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['plan_id' => 'plan', 'periodicidad_id' => 'periodicidad'];
    }
}
