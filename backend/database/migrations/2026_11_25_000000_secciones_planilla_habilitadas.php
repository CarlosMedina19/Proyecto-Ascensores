<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planilla técnica: el administrador puede desactivar una sección y volver a activarla. Desactivada no aparece
 * en las planillas nuevas ni se exige para finalizar, pero conserva sus actividades. («activo» sigue
 * significando que la sección no se ha quitado de la planilla.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_secciones', function (Blueprint $tabla) {
            $tabla->boolean('habilitada')->default(true)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_secciones', function (Blueprint $tabla) {
            $tabla->dropColumn('habilitada');
        });
    }
};
