<?php

namespace App\Services\Excel;

use RuntimeException;

/** La fila repite un código, NIT o número ya usado: no se importa. */
class FilaDuplicada extends RuntimeException
{
    public function __construct(string $mensaje, public readonly ?string $columna = null)
    {
        parent::__construct($mensaje);
    }
}
