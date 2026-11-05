<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los planes pasan a ser planes de alquiler del software, con un precio por periodo. El contrato
 * elige el plan y lo ajusta: periodicidad, precio pactado, servicios incluidos y vigencia.
 * El programa de mantenimiento de cada ascensor ya no necesita un plan: basta su periodicidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $tabla) {
            $tabla->foreignId('plan_id')->nullable()->after('cliente_id')->constrained('planes_mantenimiento')->nullOnDelete();
            $tabla->foreignId('periodicidad_id')->nullable()->after('plan_id')->constrained('periodicidades');
            $tabla->decimal('precio_periodo', 14, 2)->nullable()->after('periodicidad_id');
            $tabla->json('servicios_pactados')->nullable()->after('servicios');
        });

        Schema::table('asignaciones_plan', function (Blueprint $tabla) {
            $tabla->foreignId('plan_id')->nullable()->change();
        });

        // Sin plan, el valor por visita puede ser 0 (el servicio va incluido en el alquiler).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE asignaciones_plan DROP CONSTRAINT IF EXISTS asignaciones_precio_check');
            DB::statement('ALTER TABLE asignaciones_plan ADD CONSTRAINT asignaciones_precio_check CHECK (precio >= 0)');
        }
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('plan_id');
            $tabla->dropConstrainedForeignId('periodicidad_id');
            $tabla->dropColumn(['precio_periodo', 'servicios_pactados']);
        });
    }
};
