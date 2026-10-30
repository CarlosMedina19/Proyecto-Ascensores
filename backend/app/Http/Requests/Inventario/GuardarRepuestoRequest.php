<?php

namespace App\Http\Requests\Inventario;

use App\Models\Repuesto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF-040: datos del repuesto. El stock inicial solo se envía al crearlo; después cambia con movimientos. */
class GuardarRepuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $actual = $this->route('repuesto');
        $requerido = $actual ? ['sometimes', 'required'] : ['required'];

        return [
            'codigo' => [...$requerido, 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('repuestos', 'codigo')->ignore($actual?->id)],
            'nombre' => [...$requerido, 'string', 'max:150'],
            'marca' => ['nullable', 'string', 'max:100'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'unidad' => [...$requerido, Rule::in(array_keys(Repuesto::UNIDADES))],
            'precio' => [...$requerido, 'numeric', 'min:0', 'max:999999999999.99'],
            'stock' => [$actual ? 'prohibited' : 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'activo' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['codigo' => 'código', 'stock_minimo' => 'stock mínimo', 'stock' => 'stock inicial'];
    }

    public function messages(): array
    {
        return ['stock.prohibited' => 'El stock no se edita: registre una entrada, una salida o un ajuste.'];
    }
}
