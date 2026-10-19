<?php

namespace App\Http\Requests\Planilla;

use App\Models\FirmaOrden;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF-025 y RF-039: firma del técnico o de quien recibe el servicio (PNG en base64).
 */
class FirmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(array_keys(FirmaOrden::TIPOS))],
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'documento' => ['nullable', 'string', 'max:30'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'imagen' => ['required', 'string', 'max:700000'],
        ];
    }

    public function attributes(): array
    {
        return ['imagen' => 'firma'];
    }
}
