<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3: técnicos, agenda y órdenes de trabajo (RF-022 a RF-029).
 */
return new class extends Migration
{
    public function up(): void
    {
        // RF-022: catálogo de especialidades (configurable, no fijo en el código).
        Schema::create('especialidades', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre', 100)->unique();
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();
        });

        // RF-022: perfil del técnico (1:1 con su usuario de rol técnico).
        Schema::create('tecnicos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('usuario_id')->unique()->constrained('usuarios')->cascadeOnDelete();
            $tabla->string('tipo_documento', 10)->default('CC');
            $tabla->string('numero_documento', 30)->unique();
            $tabla->string('telefono', 30)->nullable();
            $tabla->string('disponibilidad', 20)->default('disponible');
            $tabla->string('motivo_no_disponible')->nullable();
            $tabla->date('no_disponible_hasta')->nullable();
            $tabla->text('observaciones')->nullable();
            $tabla->timestampsTz();
        });

        Schema::create('especialidad_tecnico', function (Blueprint $tabla) {
            $tabla->foreignId('tecnico_id')->constrained('tecnicos')->cascadeOnDelete();
            $tabla->foreignId('especialidad_id')->constrained('especialidades')->restrictOnDelete();
            $tabla->primary(['tecnico_id', 'especialidad_id']);
        });

        // RF-025: orden de trabajo.
        Schema::create('ordenes_trabajo', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->uuid('identificador_uuid')->unique();
            $tabla->string('numero', 20)->unique();
            $tabla->string('servicio', 20);
            $tabla->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $tabla->foreignId('edificio_id')->constrained('edificios')->restrictOnDelete();
            $tabla->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();
            $tabla->foreignId('mantenimiento_id')->nullable()->constrained('mantenimientos_programados')->nullOnDelete();
            $tabla->date('fecha');
            $tabla->time('hora');
            $tabla->unsignedSmallInteger('duracion_estimada')->default(90);
            $tabla->string('prioridad', 10)->default('media');
            $tabla->string('estado', 20)->default('programada');
            $tabla->text('descripcion')->nullable();
            $tabla->text('observaciones')->nullable();
            $tabla->text('informe')->nullable();
            $tabla->text('motivo_cancelacion')->nullable();
            // RF-028: tiempos de la orden.
            $tabla->timestampTz('inicio_en')->nullable();
            $tabla->timestampTz('fin_en')->nullable();
            $tabla->unsignedInteger('duracion_minutos')->nullable();
            $tabla->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampsTz();
            $tabla->index(['fecha', 'hora']);
            $tabla->index(['estado', 'fecha']);
            $tabla->index('equipo_id');
        });

        // RF-024: una orden puede tener varios técnicos.
        Schema::create('orden_tecnico', function (Blueprint $tabla) {
            $tabla->foreignId('orden_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $tabla->foreignId('tecnico_id')->constrained('tecnicos')->restrictOnDelete();
            $tabla->foreignId('asignado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampTz('asignado_en')->useCurrent();
            $tabla->primary(['orden_id', 'tecnico_id']);
            $tabla->index('tecnico_id');
        });

        // RF-029: trazabilidad (no se edita ni se borra).
        Schema::create('trazabilidad_ordenes', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('orden_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->string('accion', 40);
            $tabla->string('campo', 40)->nullable();
            $tabla->text('valor_anterior')->nullable();
            $tabla->text('valor_nuevo')->nullable();
            $tabla->text('detalle')->nullable();
            $tabla->timestampTz('created_at')->useCurrent();
            $tabla->index(['orden_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach ([
                "ALTER TABLE tecnicos ADD CONSTRAINT tecnicos_disponibilidad_check CHECK (disponibilidad IN ('disponible', 'no_disponible'))",
                "ALTER TABLE ordenes_trabajo ADD CONSTRAINT ordenes_servicio_check CHECK (servicio IN ('preventivo', 'correctivo', 'inspeccion'))",
                "ALTER TABLE ordenes_trabajo ADD CONSTRAINT ordenes_prioridad_check CHECK (prioridad IN ('baja', 'media', 'alta', 'critica'))",
                "ALTER TABLE ordenes_trabajo ADD CONSTRAINT ordenes_estado_check CHECK (estado IN ('programada', 'asignada', 'en_camino', 'en_proceso', 'pendiente', 'finalizada', 'cancelada'))",
                'ALTER TABLE ordenes_trabajo ADD CONSTRAINT ordenes_duracion_check CHECK (duracion_estimada BETWEEN 15 AND 720)',
                // RF-028: la finalización no puede ser anterior al inicio.
                'ALTER TABLE ordenes_trabajo ADD CONSTRAINT ordenes_tiempos_check CHECK (fin_en IS NULL OR inicio_en IS NULL OR fin_en >= inicio_en)',
                // Una visita programada genera como máximo una orden activa.
                "CREATE UNIQUE INDEX ordenes_una_por_mantenimiento ON ordenes_trabajo (mantenimiento_id) WHERE mantenimiento_id IS NOT NULL AND estado <> 'cancelada'",
            ] as $sentencia) {
                DB::statement($sentencia);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trazabilidad_ordenes');
        Schema::dropIfExists('orden_tecnico');
        Schema::dropIfExists('ordenes_trabajo');
        Schema::dropIfExists('especialidad_tecnico');
        Schema::dropIfExists('tecnicos');
        Schema::dropIfExists('especialidades');
    }
};
