<?php

namespace App\Http\Requests\Cartera;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            'numero' => ['required', 'string', 'max:40', Rule::unique('facturas', 'numero')],
            'concepto' => ['nullable', 'string', 'max:500'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['required', 'date', 'after_or_equal:fecha_emision'],
            'valor' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
        ];
    }

    public function attributes(): array
    {
        return [
            'numero' => 'número de factura',
            'fecha_emision' => 'fecha de emisión',
            'fecha_vencimiento' => 'fecha de vencimiento',
        ];
    }
}
