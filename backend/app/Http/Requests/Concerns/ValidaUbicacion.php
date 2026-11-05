<?php

namespace App\Http\Requests\Concerns;

use App\Support\Colombia;
use Illuminate\Validation\Validator;

/**
 * La ciudad debe ser un municipio del departamento indicado. Antes de validar se escriben con su
 * nombre oficial ("ibague" → "Ibagué") y, si falta el departamento, se deduce de la ciudad.
 */
trait ValidaUbicacion
{
    protected function normalizarUbicacion(): void
    {
        if (blank($this->input('ciudad'))) {
            return;
        }
        if ($ubicacion = Colombia::ubicacion($this->input('ciudad'), $this->input('departamento'))) {
            $this->merge(['ciudad' => $ubicacion[0], 'departamento' => $ubicacion[1]]);
        }
    }

    protected function validarUbicacion(Validator $validador): void
    {
        if (blank($this->input('ciudad')) || $validador->errors()->has('ciudad')) {
            return;
        }
        $departamento = $this->input('departamento');
        if (filled($departamento) && ! Colombia::departamento($departamento)) {
            $validador->errors()->add('departamento', 'Elija un departamento de la lista.');
        } elseif (! Colombia::ubicacion($this->input('ciudad'), $departamento)) {
            $validador->errors()->add('ciudad', filled($departamento)
                ? "La ciudad no es un municipio de {$departamento}."
                : 'Elija la ciudad de la lista o indique el departamento.');
        }
    }
}
