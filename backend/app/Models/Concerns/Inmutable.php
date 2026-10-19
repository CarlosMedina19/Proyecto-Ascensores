<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Registros históricos: se crean, pero no se modifican ni se eliminan.
 * Las correcciones se hacen con registros nuevos.
 */
trait Inmutable
{
    protected static function bootInmutable(): void
    {
        static::creating(function ($registro) {
            $registro->created_at ??= now();
        });
        static::updating(fn () => throw new LogicException('Este registro histórico no se puede modificar.'));
        static::deleting(fn () => throw new LogicException('Este registro histórico no se puede eliminar.'));
    }
}
