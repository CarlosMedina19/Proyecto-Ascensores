<?php

namespace App\Http\Requests\Asignacion;

use Illuminate\Foundation\Http\FormRequest;

class CambiarVigenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_fin' => ['required', 'date'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['fecha_fin' => 'fecha final'];
    }
}
