<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Hora real de un cambio hecho en la app sin conexión. La app guarda cuándo ocurrió (ocurrido_en) y lo envía
 * al recuperar la conexión; el servidor lo acepta dentro de un margen y nunca antes del paso anterior.
 */
class MomentoRegistro
{
    /**
     * Regla de validación del campo ocurrido_en: hasta 7 días atrás. Una hora futura (reloj del celular adelantado)
     * no se rechaza: se usa la del servidor.
     */
    public const REGLA = ['nullable', 'date', 'after:-7 days'];

    public static function desde(array $datos, ?CarbonInterface $minimo = null): Carbon
    {
        $ahora = now();
        $momento = filled($datos['ocurrido_en'] ?? null) ? Carbon::parse($datos['ocurrido_en'])->setTimezone($ahora->getTimezone()) : $ahora->copy();
        if ($momento->greaterThan($ahora)) {
            $momento = $ahora->copy();
        }
        if ($minimo && $momento->lessThan($minimo)) {
            $momento = Carbon::instance($minimo);
        }

        return $momento;
    }

    /** Nota para la trazabilidad cuando el cambio se hizo sin conexión (más de 2 minutos antes de llegar). */
    public static function nota(Carbon $momento): ?string
    {
        return $momento->diffInMinutes(now(), true) > 2 ? 'Registrado sin conexión el '.$momento->format('d/m/Y H:i').'.' : null;
    }
}
