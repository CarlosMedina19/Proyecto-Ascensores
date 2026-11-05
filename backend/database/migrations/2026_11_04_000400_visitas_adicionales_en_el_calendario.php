<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitas que el coordinador agrega desde el calendario en el día que elija. No se cancelan
 * cuando SIGMA vuelve a calcular la programación por la frecuencia del ascensor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mantenimientos_programados', function (Blueprint $tabla) {
            $tabla->boolean('adicional')->default(false)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('mantenimientos_programados', function (Blueprint $tabla) {
            $tabla->dropColumn('adicional');
        });
    }
};
