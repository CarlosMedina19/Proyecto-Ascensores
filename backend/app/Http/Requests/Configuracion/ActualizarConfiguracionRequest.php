<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Parámetros configurables por el administrador (RF-011, RF-020, RF-021).
 */
class ActualizarConfiguracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notificaciones_canales' => ['sometimes', 'array', 'min:1'],
            'notificaciones_canales.*' => ['string', 'distinct', Rule::in(['database', 'mail'])],
            'notificaciones_dias_aviso' => ['sometimes', 'array', 'min:1', 'max:10'],
            'notificaciones_dias_aviso.*' => ['integer', 'distinct', 'min:0', 'max:90'],
            'contratos_dias_alerta_vencimiento' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'iva_porcentaje' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'emergencias_minutos_objetivo' => ['sometimes', 'integer', 'min:5', 'max:1440'],
            'emergencias_minutos_atrapamiento' => ['sometimes', 'integer', 'min:5', 'max:1440'],
            'certificaciones_dias_alerta' => ['sometimes', 'array', 'min:1', 'max:6'],
            'certificaciones_dias_alerta.*' => ['integer', 'distinct', 'min:1', 'max:180'],
        ];
    }

    public function attributes(): array
    {
        return [
            'notificaciones_canales' => 'canales de envío',
            'notificaciones_dias_aviso' => 'días de aviso',
            'contratos_dias_alerta_vencimiento' => 'días de alerta de vencimiento',
            'iva_porcentaje' => 'porcentaje de IVA',
            'emergencias_minutos_objetivo' => 'tiempo objetivo de emergencias',
            'emergencias_minutos_atrapamiento' => 'tiempo objetivo con personas atrapadas',
            'certificaciones_dias_alerta' => 'días de alerta de la certificación',
        ];
    }

    /** Claves de la configuración ("notificaciones.canales") a partir de los campos enviados. */
    public function valores(): array
    {
        $valores = [];
        foreach ($this->validated() as $campo => $valor) {
            $clave = preg_replace('/_/', '.', $campo, 1);
            $valores[$clave] = is_array($valor) ? array_values($valor) : $valor;
        }

        return $valores;
    }
}
