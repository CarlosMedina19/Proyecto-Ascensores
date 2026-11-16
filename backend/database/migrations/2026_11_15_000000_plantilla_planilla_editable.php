<?php

use App\Support\PlantillaPlanilla;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planilla de asistencia técnica editable: las secciones pasan a una tabla y se instala la plantilla
 * del formato en papel (45 actividades en 6 secciones). Las actividades anteriores quedan inactivas
 * para no alterar las planillas ya diligenciadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_secciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('clave', 40)->unique();
            $tabla->string('nombre', 100);
            $tabla->unsignedSmallInteger('orden')->default(1);
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();
        });

        Schema::table('checklist_items', function (Blueprint $tabla) {
            $tabla->string('seccion', 40)->change();
            $tabla->string('nombre', 200)->change();
        });

        PlantillaPlanilla::instalar();
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_secciones');
    }
};
