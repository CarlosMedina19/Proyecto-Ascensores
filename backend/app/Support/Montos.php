<?php

namespace App\Support;

/**
 * Operaciones con valores monetarios (NUMERIC(14,2)) sin depender de la extensión bcmath.
 * Se trabaja en centavos enteros para evitar errores de redondeo de punto flotante.
 */
final class Montos
{
    public static function aCentavos(string|int|float|null $valor): int
    {
        return (int) round(((float) $valor) * 100);
    }

    public static function iguales(string|int|float|null $a, string|int|float|null $b): bool
    {
        return self::aCentavos($a) === self::aCentavos($b);
    }
}
