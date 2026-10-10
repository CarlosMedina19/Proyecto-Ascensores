<?php

use App\Database\DatabaseNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $nombreIngles = 'electric_doors';
        $nombreEspanol = DatabaseNames::tables()[$nombreIngles];
        $existeIngles = Schema::hasTable($nombreIngles);
        $existeEspanol = Schema::hasTable($nombreEspanol);

        if ($existeIngles && $existeEspanol) {
            throw new RuntimeException("Existen ambas tablas {$nombreIngles} y {$nombreEspanol}.");
        }

        if ($existeIngles) {
            Schema::rename($nombreIngles, $nombreEspanol);
        } elseif (! $existeEspanol) {
            return;
        }

        $this->renombrarColumnas($nombreEspanol, DatabaseNames::columns());
    }

    public function down(): void
    {
        $nombreIngles = 'electric_doors';
        $nombreEspanol = DatabaseNames::tables()[$nombreIngles];
        $tabla = Schema::hasTable($nombreEspanol) ? $nombreEspanol : $nombreIngles;

        if (! Schema::hasTable($tabla)) {
            return;
        }

        $this->renombrarColumnas($tabla, array_flip(DatabaseNames::columns()));

        if ($tabla === $nombreEspanol) {
            if (Schema::hasTable($nombreIngles)) {
                throw new RuntimeException("Existen ambas tablas {$nombreIngles} y {$nombreEspanol}.");
            }

            Schema::rename($nombreEspanol, $nombreIngles);
        }
    }

    private function renombrarColumnas(string $tabla, array $nombres): void
    {
        $columnas = Schema::getColumnListing($tabla);

        foreach ($nombres as $origen => $destino) {
            if ($origen === $destino) {
                continue;
            }

            $existeOrigen = in_array($origen, $columnas, true);
            $existeDestino = in_array($destino, $columnas, true);

            if ($existeOrigen && $existeDestino) {
                throw new RuntimeException("La tabla {$tabla} contiene ambas columnas {$origen} y {$destino}.");
            }

            if (! $existeOrigen) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $definicion) use ($origen, $destino): void {
                $definicion->renameColumn($origen, $destino);
            });

            $columnas[array_search($origen, $columnas, true)] = $destino;
        }
    }
};
