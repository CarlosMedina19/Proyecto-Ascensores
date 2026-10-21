<?php

namespace App\Http\Requests\Servicio;

use App\Models\Certificacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Certificación anual de un ascensor (formulario y hoja Certificaciones del Excel). */
class GuardarCertificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organismo' => ['required', 'string', 'max:150'],
            'numero_certificado' => ['nullable', 'string', 'max:60'],
            'norma' => ['nullable', 'string', 'max:80'],
            'fecha_inspeccion' => ['required', 'date', 'before_or_equal:today'],
            'fecha_vencimiento' => ['required', 'date', 'after:fecha_inspeccion'],
            'resultado' => ['required', Rule::in(array_keys(Certificacion::RESULTADOS))],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function attributes(): array
    {
        return [
            'organismo' => 'organismo de inspección',
            'numero_certificado' => 'número del certificado',
            'fecha_inspeccion' => 'fecha de inspección',
            'fecha_vencimiento' => 'fecha de vencimiento',
            'archivo' => 'certificado',
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.mimes' => 'El certificado debe ser PDF, JPG o PNG.',
            'archivo.max' => 'El certificado no puede pesar más de 10 MB.',
            'fecha_inspeccion.before_or_equal' => 'La fecha de inspección no puede ser futura.',
        ];
    }
}
