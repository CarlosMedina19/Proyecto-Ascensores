<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planilla de asistencia técnica que el técnico diligencia en cada visita (formato en papel digitalizado).
 * Guarda una copia de la plantilla con lo marcado: si después se edita la plantilla, la planilla no cambia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitas_tecnicas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('numero', 20)->nullable()->unique();
            $tabla->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $tabla->foreignId('edificio_id')->nullable()->constrained('edificios')->nullOnDelete();
            $tabla->foreignId('tecnico_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->foreignId('orden_id')->nullable()->constrained('ordenes_trabajo')->nullOnDelete();
            $tabla->string('tipo_servicio', 20);
            $tabla->timestampTz('entrada_en');
            $tabla->timestampTz('salida_en');
            $tabla->json('secciones');
            $tabla->text('observaciones')->nullable();
            $tabla->json('repuestos')->nullable();
            $tabla->string('recibe_nombre', 150);
            $tabla->string('recibe_documento', 30);
            $tabla->string('tecnico_documento', 30)->nullable();
            $tabla->string('firma_tecnico_ruta')->nullable();
            $tabla->string('firma_recibe_ruta')->nullable();
            $tabla->string('pdf_ruta')->nullable();
            $tabla->timestampsTz();
            $tabla->index(['equipo_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas_tecnicas');
    }
};
