<?php

namespace App\Http\Requests\Cliente;

use App\Http\Requests\Concerns\MapearCamposApi;
use Illuminate\Foundation\Http\FormRequest;

class GuardarContactoClienteRequest extends FormRequest
{
    use MapearCamposApi;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->mapApiFields([
            'nombre' => 'nombre',
            'cargo' => 'cargo',
            'telefono' => 'telefono',
            'correo' => 'correo',
        ], $this->all()));
    }
}
