<?php

namespace App\Http\Requests\Contrato;

use App\Models\Contrato;
use Illuminate\Validation\Validator;

class ActualizarContratoRequest extends GuardarContratoRequest
{
    protected function contratoActual(): ?Contrato
    {
        return $this->route('contrato');
    }

    public function rules(): array
    {
        $reglas = parent::rules();
        // Si solo cambia una de las fechas, se compara contra la guardada (ver after()).
        $reglas['fecha_fin'] = ['sometimes', 'required', 'date'];

        return $reglas;
    }

    public function after(): array
    {
        return [
            function (Validator $validador) {
                $contrato = $this->contratoActual();
                $inicio = $this->input('fecha_inicio', $contrato->fecha_inicio->toDateString());
                $fin = $this->input('fecha_fin', $contrato->fecha_fin->toDateString());
                if (strtotime((string) $fin) <= strtotime((string) $inicio)) {
                    $validador->errors()->add('fecha_fin', 'La fecha final debe ser posterior a la fecha inicial.');
                }
            },
        ];
    }
}
