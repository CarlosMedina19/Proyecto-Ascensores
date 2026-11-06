<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SIGMA tiene un solo plan de alquiler: el precio cambia según el tiempo que se alquile.
 * Queda el primer plan; los contratos de los demás pasan a él y los demás se retiran del catálogo.
 * El mantenimiento de los ascensores ya no depende de un plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('asignaciones_plan')->update(['plan_id' => null]);

        $plan = DB::table('planes_mantenimiento')->whereNull('deleted_at')->orderBy('id')->first();
        if (! $plan) {
            return;
        }
        DB::table('contratos')->whereNotNull('plan_id')->update(['plan_id' => $plan->id]);
        DB::table('planes_mantenimiento')->whereNull('deleted_at')->where('id', '!=', $plan->id)->update(['deleted_at' => now(), 'activo' => false]);
        DB::table('planes_mantenimiento')->where('id', $plan->id)->update(['nombre' => 'Plan SIGMA', 'activo' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Los planes retirados se conservan en la tabla, pero no se vuelven a activar.
    }
};
