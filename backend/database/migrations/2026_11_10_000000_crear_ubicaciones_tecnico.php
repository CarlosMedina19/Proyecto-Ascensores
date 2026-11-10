<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posiciones que envía el técnico (app o navegador) mientras una orden está en proceso.
 * Se usan para el mapa en vivo de Auditoría; se guardan con la orden para su trazabilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubicaciones_tecnico', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('tecnico_id')->constrained('tecnicos')->cascadeOnDelete();
            $tabla->foreignId('orden_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $tabla->decimal('latitud', 10, 7);
            $tabla->decimal('longitud', 10, 7);
            $tabla->decimal('precision_metros', 8, 1)->nullable();
            $tabla->string('origen', 10)->default('app');
            $tabla->timestampTz('registrada_en');
            $tabla->index(['orden_id', 'registrada_en']);
            $tabla->index(['tecnico_id', 'registrada_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ubicaciones_tecnico');
    }
};
