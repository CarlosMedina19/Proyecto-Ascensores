<?php

namespace App\Http\Requests\Equipo;

use App\Models\FotoEquipo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Fotos del ascensor: JPG, PNG o WEBP de máximo 5 MB, hasta 3 por ascensor.
 */
class SubirFotosEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fotos' => ['required', 'array', 'min:1', 'max:'.FotoEquipo::MAXIMO_POR_EQUIPO],
            'fotos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return ['fotos' => 'fotos', 'fotos.*' => 'foto'];
    }

    public function messages(): array
    {
        return [
            'fotos.max' => 'Puede subir máximo '.FotoEquipo::MAXIMO_POR_EQUIPO.' fotos por ascensor.',
            'fotos.*.mimes' => 'Cada foto debe ser una imagen JPG, PNG o WEBP.',
            'fotos.*.mimetypes' => 'Cada foto debe ser una imagen JPG, PNG o WEBP.',
            'fotos.*.max' => 'Cada foto puede pesar máximo 5 MB.',
            'fotos.*.uploaded' => 'La foto no se pudo subir. Verifique que pese menos de 5 MB.',
        ];
    }
}
