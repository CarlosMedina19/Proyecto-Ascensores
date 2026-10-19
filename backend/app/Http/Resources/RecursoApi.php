<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso base: las respuestas individuales se envuelven en "datos"
 * y las colecciones usan ColeccionApi (datos, enlaces y metadatos).
 */
abstract class RecursoApi extends JsonResource
{
    public static $wrap = 'datos';

    protected static function newCollection($resource)
    {
        return new ColeccionApi($resource, static::class);
    }
}
