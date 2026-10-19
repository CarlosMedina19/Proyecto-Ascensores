<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Se lanza cuando una operación viola una regla de negocio de los requerimientos
 * (por ejemplo, eliminar un edificio con equipos activos). Responde 422.
 */
class ReglaNegocioException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['mensaje' => $this->getMessage()], 422);
    }
}
