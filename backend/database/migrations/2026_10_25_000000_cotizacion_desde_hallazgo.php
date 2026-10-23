<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una cotización puede nacer de un ítem en Regular o Malo de la planilla (botón «Cotizar»). La orden que
 * ejecuta el trabajo aprobado se guarda en orden_id, igual que la orden que atiende una emergencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $tabla) {
            $tabla->foreignId('resultado_origen_id')->nullable()->after('orden_id')->constrained('planilla_resultados')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('resultado_origen_id');
        });
    }
};
