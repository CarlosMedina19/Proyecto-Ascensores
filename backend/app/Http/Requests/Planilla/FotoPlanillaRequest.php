<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FotoPlanillaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'item_id' => ['nullable', 'integer', Rule::exists('checklist_items', 'id')],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'foto.mimes' => 'La foto debe ser una imagen JPG, PNG o WEBP.',
            'foto.mimetypes' => 'La foto debe ser una imagen JPG, PNG o WEBP.',
            'foto.max' => 'La foto puede pesar máximo 5 MB.',
        ];
    }
}
