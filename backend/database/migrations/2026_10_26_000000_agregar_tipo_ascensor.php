<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tipo de ascensor según su uso (pasajeros, carga, camillero…), distinto del accionamiento. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ascensores', function (Blueprint $tabla) {
            $tabla->string('tipo_ascensor', 30)->nullable()->after('equipo_id');
        });
    }

    public function down(): void
    {
        Schema::table('ascensores', function (Blueprint $tabla) {
            $tabla->dropColumn('tipo_ascensor');
        });
    }
};
