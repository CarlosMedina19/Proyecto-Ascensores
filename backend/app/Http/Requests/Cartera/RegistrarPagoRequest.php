<?php

namespace App\Http\Requests\Cartera;

use App\Models\Pago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'factura_id' => ['required', 'integer', Rule::exists('facturas', 'id')],
            'valor' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            // El medio PSE solo lo registra la pasarela cuando aprueba el pago.
            'medio' => ['required', Rule::in(array_diff(array_keys(Pago::MEDIOS), ['pse']))],
            'referencia' => ['required', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['factura_id' => 'factura', 'medio' => 'medio de pago'];
    }
}
