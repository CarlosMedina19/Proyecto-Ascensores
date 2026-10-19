<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Asigna el identificador público (UUID) al crear el registro.
 */
trait TieneUuid
{
    protected static function bootTieneUuid(): void
    {
        static::creating(function ($modelo) {
            $modelo->identificador_uuid ??= (string) Str::uuid();
        });
    }
}
