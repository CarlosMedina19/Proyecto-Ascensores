<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Fotos del ascensor (máximo 3, guardadas en el disco privado).
 * - Hora de las visitas: cada plan tiene una hora preferida que se copia a sus visitas;
 *   al reprogramar se puede cambiar la fecha y la hora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fotos_equipo', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $tabla->string('ruta');
            $tabla->string('nombre_original');
            $tabla->string('tipo_mime', 50);
            $tabla->unsignedInteger('tamano');
            $tabla->unsignedSmallInteger('orden')->default(1);
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampsTz();
            $tabla->index(['equipo_id', 'orden']);
        });

        Schema::table('asignaciones_plan', function (Blueprint $tabla) {
            $tabla->time('hora_preferida')->default('08:00');
        });

        Schema::table('mantenimientos_programados', function (Blueprint $tabla) {
            $tabla->time('hora')->nullable();
        });

        // Las visitas que ya existen toman la hora de su plan.
        DB::table('mantenimientos_programados')->update(['hora' => '08:00']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE asignaciones_plan ADD CONSTRAINT asignaciones_hora_check CHECK (hora_preferida BETWEEN '06:00' AND '18:00')");
        }
    }

    public function down(): void
    {
        Schema::table('mantenimientos_programados', fn (Blueprint $tabla) => $tabla->dropColumn('hora'));
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE asignaciones_plan DROP CONSTRAINT IF EXISTS asignaciones_hora_check');
        }
        Schema::table('asignaciones_plan', fn (Blueprint $tabla) => $tabla->dropColumn('hora_preferida'));
        Schema::dropIfExists('fotos_equipo');
    }
};
