<?php

use App\Database\DatabaseNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DatabaseNames::tables() as $english => $spanish) {
            $hasEnglish = Schema::hasTable($english);
            $hasSpanish = Schema::hasTable($spanish);

            if (! $hasEnglish && ! $hasSpanish && $english === 'electric_doors') {
                continue;
            }

            if ($english !== $spanish && $hasEnglish && $hasSpanish) {
                throw new \RuntimeException("Existen ambas tablas {$english} y {$spanish}.");
            }

            if ($english !== $spanish && $hasEnglish) {
                Schema::rename($english, $spanish);
            } elseif (! $hasSpanish) {
                throw new \RuntimeException("No se encontró la tabla esperada: {$english}.");
            }
        }

        foreach (DatabaseNames::tables() as $english => $spanish) {
            if (Schema::hasTable($spanish)) {
                $this->renameColumns($spanish, DatabaseNames::columns());
            }
        }
    }

    public function down(): void
    {
        $reverseColumns = array_flip(DatabaseNames::columns());

        foreach (DatabaseNames::tables() as $english => $spanish) {
            $table = Schema::hasTable($spanish) ? $spanish : $english;
            if (Schema::hasTable($table)) {
                $this->renameColumns($table, $reverseColumns);
            }
        }

        foreach (array_reverse(DatabaseNames::tables(), true) as $english => $spanish) {
            if ($english !== $spanish && Schema::hasTable($spanish)) {
                Schema::rename($spanish, $english);
            }
        }
    }

    private function renameColumns(string $tableName, array $columnNames): void
    {
        $existing = Schema::getColumnListing($tableName);

        foreach ($columnNames as $source => $destination) {
            if ($source === $destination) {
                continue;
            }

            $hasSource = in_array($source, $existing, true);
            $hasDestination = in_array($destination, $existing, true);

            if ($hasSource && $hasDestination) {
                throw new \RuntimeException(
                    "La tabla {$tableName} contiene ambas columnas {$source} y {$destination}."
                );
            }

            if (! $hasSource) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($source, $destination): void {
                $table->renameColumn($source, $destination);
            });

            $existing[array_search($source, $existing, true)] = $destination;
        }
    }
};
