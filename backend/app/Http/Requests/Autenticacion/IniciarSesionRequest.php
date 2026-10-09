<?php

namespace App\Http\Requests\Autenticacion;

use App\Http\Requests\Concerns\MapearCamposApi;
use Illuminate\Foundation\Http\FormRequest;

class IniciarSesionRequest extends FormRequest
{
    use MapearCamposApi;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'correo' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->mapApiFields(['correo' => 'correo'], $this->all()));
    }
}
