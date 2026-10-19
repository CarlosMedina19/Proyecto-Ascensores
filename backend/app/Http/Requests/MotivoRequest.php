<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Acciones que solo exigen un motivo (cancelar, finalizar...). El motivo queda en el historial.
 */
class MotivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
