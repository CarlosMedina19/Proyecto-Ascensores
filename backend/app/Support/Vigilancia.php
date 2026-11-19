<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Registro de las tareas que la vigilancia revisa (por ejemplo, el respaldo remoto): cuándo salió bien
 * por última vez y el último error. Se guarda en la caché de la base, que sobrevive a los despliegues.
 */
class Vigilancia
{
    public static function registrarExito(string $tarea): void
    {
        Cache::forever("vigilancia:exito:{$tarea}", now()->toIso8601String());
        Cache::forget("vigilancia:fallo:{$tarea}");
    }

    public static function registrarFallo(string $tarea, string $detalle): void
    {
        Cache::forever("vigilancia:fallo:{$tarea}", ['en' => now()->toIso8601String(), 'detalle' => $detalle]);
    }

    public static function ultimoExito(string $tarea): ?Carbon
    {
        $valor = Cache::get("vigilancia:exito:{$tarea}");

        return $valor ? Carbon::parse($valor) : null;
    }

    /** @return array{en: string, detalle: string}|null */
    public static function ultimoFallo(string $tarea): ?array
    {
        return Cache::get("vigilancia:fallo:{$tarea}");
    }
}
