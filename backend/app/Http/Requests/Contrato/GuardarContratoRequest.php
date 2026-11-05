<?php

namespace App\Http\Requests\Contrato;

use App\Models\Contrato;
use App\Models\PlanMantenimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * RF-019: contrato con cliente, al menos un equipo, vigencia, valor y condiciones.
 * El plan de alquiler se ajusta en el contrato: periodicidad, precio por periodo y servicios incluidos;
 * sin precio se toma el del catálogo y sin valor total se calcula con los periodos de la vigencia.
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
            'plan_id' => ['nullable', 'integer', Rule::exists('planes_mantenimiento', 'id')->whereNull('deleted_at')],
            'periodicidad_id' => ['nullable', 'required_with:plan_id', 'integer', Rule::exists('periodicidades', 'id')->where('activo', true)],
            'precio_periodo' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'servicios_pactados' => ['nullable', 'array', 'max:40'],
            'servicios_pactados.*' => ['string', 'distinct', 'max:200'],
            'valor' => [$actual ? 'sometimes' : 'required_without:plan_id', 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'servicios' => ['nullable', 'string', 'max:5000'],
            'condiciones' => ['nullable', 'string', 'max:10000'],
            'equipos' => [...$requerido, 'array', 'min:1'],
            'equipos.*' => ['integer', 'distinct', Rule::exists('equipos', 'id')->whereNull('deleted_at')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validador) {
                if (! $this->filled('plan_id') || $this->filled('precio_periodo') || $validador->errors()->hasAny(['plan_id', 'periodicidad_id'])) {
                    return;
                }
                $plan = PlanMantenimiento::find($this->input('plan_id'));
                if ($plan && $plan->precioPara((int) $this->input('periodicidad_id')) === null) {
                    $validador->errors()->add('precio_periodo', "El plan «{$plan->nombre}» no tiene precio para esa periodicidad. Escriba el precio pactado.");
                }
            },
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
            'plan_id' => 'plan',
            'periodicidad_id' => 'periodicidad',
            'precio_periodo' => 'precio por periodo',
            'servicios_pactados.*' => 'servicio',
        ];
    }

    public function messages(): array
    {
        return [
            'equipos.min' => 'El contrato debe incluir al menos un ascensor.',
            'valor.required_without' => 'Indique el valor del contrato o elija un plan para calcularlo.',
            'periodicidad_id.required_with' => 'Elija la periodicidad del plan.',
            'cliente_id.prohibited' => 'El cliente de un contrato no se puede cambiar.',
        ];
    }
}
