<?php

namespace App\Http\Requests\Contrato;

use App\Models\Contrato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RenovarContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['required', 'date'],
            'valor' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'servicios' => ['nullable', 'string', 'max:5000'],
            'condiciones' => ['nullable', 'string', 'max:10000'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validador) {
                /** @var Contrato $contrato */
                $contrato = $this->route('contrato');
                $inicio = $this->input('fecha_inicio') ?? $contrato->fecha_fin->copy()->addDay()->toDateString();
                if ($this->filled('fecha_fin') && strtotime((string) $this->input('fecha_fin')) <= strtotime((string) $inicio)) {
                    $validador->errors()->add('fecha_fin', 'La fecha final de la renovación debe ser posterior a su fecha inicial.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return ['fecha_inicio' => 'fecha inicial', 'fecha_fin' => 'fecha final'];
    }
}
