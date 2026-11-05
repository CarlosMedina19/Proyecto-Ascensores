<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Días y horas de trabajo de cada técnico: {"1": {"inicio": "07:00", "fin": "17:00"}, …} (1 = lunes).
 * Sin horario, el técnico puede recibir órdenes a cualquier hora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tecnicos', function (Blueprint $tabla) {
            $tabla->json('horario')->nullable()->after('disponibilidad');
        });
    }

    public function down(): void
    {
        Schema::table('tecnicos', function (Blueprint $tabla) {
            $tabla->dropColumn('horario');
        });
    }
};
