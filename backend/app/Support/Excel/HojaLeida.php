<?php

namespace App\Support\Excel;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/** Filas crudas de una hoja (la primera es el encabezado). */
class HojaLeida implements ToArray, WithCalculatedFormulas, WithCustomCsvSettings
{
    /** @var array<int, array<int, mixed>> */
    public array $filas = [];

    public function __construct(private bool $csv = false, private string $delimitador = ',', private string $codificacion = 'UTF-8') {}

    public function array(array $filas): void
    {
        $this->filas = $filas;
    }

    public function getCsvSettings(): array
    {
        return $this->csv ? ['delimiter' => $this->delimitador, 'input_encoding' => $this->codificacion] : [];
    }
}
