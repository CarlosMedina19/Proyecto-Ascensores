<?php

namespace App\Services\Excel;

use RuntimeException;

/** Errores de una fila de la plantilla, con la columna de cada uno. */
class FilaInvalida extends RuntimeException
{
    /** @param  array<int, array{columna: ?string, motivo: string}>  $errores */
    public function __construct(public readonly array $errores)
    {
        parent::__construct(implode(' ', array_column($errores, 'motivo')));
    }
}
