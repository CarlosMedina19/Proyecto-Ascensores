<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Parámetros del sistema que el administrador cambia sin tocar el código.
 */
class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = ['clave', 'valor', 'descripcion'];

    /** Valores por defecto y descripción de cada parámetro editable. */
    public const PARAMETROS = [
        'notificaciones.canales' => [
            'valor' => ['database', 'mail'],
            'descripcion' => 'Canales de envío de los avisos: database (en la aplicación) y mail (correo).',
        ],
        'notificaciones.dias_aviso' => [
            'valor' => [15, 7, 1, 0],
            'descripcion' => 'Días antes del mantenimiento en que se avisa (0 = el mismo día).',
        ],
        'contratos.dias_alerta_vencimiento' => [
            'valor' => 30,
            'descripcion' => 'Días antes del vencimiento de un contrato en que se genera la alerta de renovación.',
        ],
        'iva.porcentaje' => [
            'valor' => 19,
            'descripcion' => 'Porcentaje de IVA sugerido para las nuevas asignaciones de plan.',
        ],
    ];

    protected function casts(): array
    {
        return ['valor' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('sigma.configuracion'));
    }

    public static function valor(string $clave): mixed
    {
        $guardadas = Cache::remember(
            'sigma.configuracion',
            300,
            fn () => static::query()->get()->mapWithKeys(fn (Configuracion $c) => [$c->clave => $c->valor])->all()
        );

        return array_key_exists($clave, $guardadas) ? $guardadas[$clave] : (self::PARAMETROS[$clave]['valor'] ?? null);
    }
}
