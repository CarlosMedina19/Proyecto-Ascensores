<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 de SIGMA: planes, periodicidad, precios, contratos, programación y notificaciones
 * (RF-010 a RF-021).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Parámetros del sistema editables por el administrador (canales, días de aviso, IVA...).
        Schema::create('configuraciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('clave', 100)->unique();
            $tabla->json('valor');
            $tabla->string('descripcion')->nullable();
            $tabla->timestamps();
        });

        // RF-011: periodicidades configurables, sin valores fijos en el código.
        Schema::create('periodicidades', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->unsignedSmallInteger('meses')->unique();
            $tabla->string('nombre', 60);
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });

        // RF-010: catálogo de planes.
        Schema::create('planes_mantenimiento', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->uuid('identificador_uuid')->unique();
            $tabla->string('nombre', 150)->unique();
            $tabla->text('descripcion')->nullable();
            $tabla->json('servicios_incluidos')->nullable();
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
            $tabla->softDeletes();
        });

        // RF-012: un precio por periodicidad en cada plan.
        Schema::create('precios_plan', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('plan_id')->constrained('planes_mantenimiento')->cascadeOnDelete();
            $tabla->foreignId('periodicidad_id')->constrained('periodicidades')->restrictOnDelete();
            $tabla->decimal('precio', 14, 2);
            $tabla->timestamps();
            $tabla->unique(['plan_id', 'periodicidad_id']);
        });

        // RF-019: contratos.
        Schema::create('contratos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->uuid('identificador_uuid')->unique();
            $tabla->string('numero', 30)->unique();
            $tabla->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $tabla->foreignId('contrato_anterior_id')->nullable()->constrained('contratos')->nullOnDelete();
            $tabla->date('fecha_inicio');
            $tabla->date('fecha_fin');
            $tabla->decimal('valor', 14, 2);
            $tabla->text('servicios')->nullable();
            $tabla->text('condiciones')->nullable();
            $tabla->string('estado', 20)->default('vigente');
            $tabla->string('estado_renovacion', 20)->default('pendiente');
            $tabla->timestampTz('alerta_vencimiento_en')->nullable();
            $tabla->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamps();
            $tabla->index('cliente_id');
            $tabla->index(['estado', 'fecha_fin']);
        });

        Schema::create('contrato_equipos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $tabla->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();
            $tabla->timestamps();
            $tabla->unique(['contrato_id', 'equipo_id']);
            $tabla->index('equipo_id');
        });

        // RF-020: seguimiento de la renovación (no se edita ni se elimina).
        Schema::create('seguimientos_renovacion', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->string('resultado', 20);
            $tabla->text('observacion')->nullable();
            $tabla->timestampTz('created_at')->useCurrent();
            $tabla->index('contrato_id');
        });

        // RF-013 y RF-014: plan asignado a un ascensor con su vigencia.
        Schema::create('asignaciones_plan', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->uuid('identificador_uuid')->unique();
            $tabla->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();
            $tabla->foreignId('plan_id')->constrained('planes_mantenimiento')->restrictOnDelete();
            $tabla->foreignId('periodicidad_id')->constrained('periodicidades')->restrictOnDelete();
            $tabla->foreignId('contrato_id')->nullable()->constrained('contratos')->nullOnDelete();
            $tabla->decimal('precio', 14, 2);
            $tabla->boolean('aplica_iva')->default(true);
            $tabla->decimal('porcentaje_iva', 5, 2)->default(0);
            $tabla->date('fecha_inicio');
            $tabla->date('fecha_fin');
            $tabla->string('estado', 20)->default('activa');
            $tabla->date('ultimo_mantenimiento')->nullable();
            $tabla->date('proximo_mantenimiento')->nullable();
            $tabla->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamps();
            $tabla->index('equipo_id');
            $tabla->index('plan_id');
            $tabla->index(['estado', 'fecha_fin']);
        });

        // RF-016: cambios de plan, periodicidad o estado de la asignación.
        Schema::create('cambios_asignacion', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('asignacion_id')->constrained('asignaciones_plan')->cascadeOnDelete();
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->string('campo', 40);
            $tabla->string('valor_anterior')->nullable();
            $tabla->string('valor_nuevo')->nullable();
            $tabla->text('motivo');
            $tabla->timestampTz('created_at')->useCurrent();
            $tabla->index(['asignacion_id', 'created_at']);
        });

        // RF-015: historial de precios del catálogo y de las asignaciones.
        Schema::create('historial_precios', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('plan_id')->nullable()->constrained('planes_mantenimiento')->nullOnDelete();
            $tabla->foreignId('periodicidad_id')->nullable()->constrained('periodicidades')->nullOnDelete();
            $tabla->foreignId('asignacion_id')->nullable()->constrained('asignaciones_plan')->cascadeOnDelete();
            $tabla->decimal('precio_anterior', 14, 2)->nullable();
            $tabla->decimal('precio_nuevo', 14, 2);
            $tabla->text('motivo');
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampTz('created_at')->useCurrent();
            $tabla->index(['plan_id', 'created_at']);
            $tabla->index(['asignacion_id', 'created_at']);
        });

        // RF-017 y RF-018: mantenimientos programados.
        Schema::create('mantenimientos_programados', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->uuid('identificador_uuid')->unique();
            $tabla->foreignId('asignacion_id')->constrained('asignaciones_plan')->cascadeOnDelete();
            $tabla->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();
            $tabla->date('fecha_programada');
            $tabla->date('fecha_teorica');
            $tabla->decimal('precio', 14, 2);
            $tabla->string('estado', 20)->default('programado');
            $tabla->date('fecha_ejecucion')->nullable();
            $tabla->text('motivo')->nullable();
            $tabla->text('observacion')->nullable();
            $tabla->foreignId('actualizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamps();
            $tabla->index(['fecha_programada', 'estado']);
            $tabla->index(['equipo_id', 'fecha_programada']);
            $tabla->index('asignacion_id');
        });

        // RF-021: cada aviso enviado queda registrado con su fecha y estado; no se repite.
        Schema::create('envios_notificacion', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('tipo', 100);
            $tabla->string('referencia_tipo', 40);
            $tabla->unsignedBigInteger('referencia_id');
            $tabla->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $tabla->string('canal', 20);
            $tabla->string('estado', 20)->default('pendiente');
            $tabla->text('error')->nullable();
            $tabla->timestampTz('enviado_en')->nullable();
            $tabla->timestampTz('created_at')->useCurrent();
            $tabla->unique(['tipo', 'referencia_tipo', 'referencia_id', 'usuario_id', 'canal'], 'envios_notificacion_unico');
            $tabla->index(['estado', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $restricciones = [
                'ALTER TABLE periodicidades ADD CONSTRAINT periodicidades_meses_check CHECK (meses BETWEEN 1 AND 24)',
                'ALTER TABLE precios_plan ADD CONSTRAINT precios_plan_precio_check CHECK (precio > 0)',
                "ALTER TABLE contratos ADD CONSTRAINT contratos_estado_check CHECK (estado IN ('vigente', 'vencido', 'renovado', 'cancelado'))",
                "ALTER TABLE contratos ADD CONSTRAINT contratos_estado_renovacion_check CHECK (estado_renovacion IN ('pendiente', 'contactado', 'renovado', 'no_renovado'))",
                'ALTER TABLE contratos ADD CONSTRAINT contratos_fechas_check CHECK (fecha_fin > fecha_inicio)',
                'ALTER TABLE contratos ADD CONSTRAINT contratos_valor_check CHECK (valor >= 0)',
                "ALTER TABLE seguimientos_renovacion ADD CONSTRAINT seguimientos_resultado_check CHECK (resultado IN ('contactado', 'renovado', 'no_renovado'))",
                'ALTER TABLE asignaciones_plan ADD CONSTRAINT asignaciones_fechas_check CHECK (fecha_fin > fecha_inicio)',
                'ALTER TABLE asignaciones_plan ADD CONSTRAINT asignaciones_precio_check CHECK (precio > 0)',
                'ALTER TABLE asignaciones_plan ADD CONSTRAINT asignaciones_iva_check CHECK (porcentaje_iva >= 0 AND porcentaje_iva <= 100)',
                "ALTER TABLE asignaciones_plan ADD CONSTRAINT asignaciones_estado_check CHECK (estado IN ('activa', 'finalizada', 'cancelada'))",
                // RF-013: un ascensor tiene un solo plan activo a la vez.
                "CREATE UNIQUE INDEX asignaciones_plan_un_activo_por_equipo ON asignaciones_plan (equipo_id) WHERE estado = 'activa'",
                'ALTER TABLE historial_precios ADD CONSTRAINT historial_precios_nuevo_check CHECK (precio_nuevo > 0)',
                'ALTER TABLE mantenimientos_programados ADD CONSTRAINT mantenimientos_precio_check CHECK (precio >= 0)',
                "ALTER TABLE mantenimientos_programados ADD CONSTRAINT mantenimientos_estado_check CHECK (estado IN ('programado', 'reprogramado', 'ejecutado', 'cancelado'))",
                // Una sola visita pendiente por fecha teórica; las ejecutadas y canceladas quedan como historial.
                "CREATE UNIQUE INDEX mantenimientos_unico_activo ON mantenimientos_programados (asignacion_id, fecha_teorica) WHERE estado IN ('programado', 'reprogramado')",
                "ALTER TABLE envios_notificacion ADD CONSTRAINT envios_estado_check CHECK (estado IN ('pendiente', 'enviado', 'fallido'))",
            ];

            foreach ($restricciones as $sql) {
                DB::statement($sql);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_notificacion');
        Schema::dropIfExists('mantenimientos_programados');
        Schema::dropIfExists('historial_precios');
        Schema::dropIfExists('cambios_asignacion');
        Schema::dropIfExists('asignaciones_plan');
        Schema::dropIfExists('seguimientos_renovacion');
        Schema::dropIfExists('contrato_equipos');
        Schema::dropIfExists('contratos');
        Schema::dropIfExists('precios_plan');
        Schema::dropIfExists('planes_mantenimiento');
        Schema::dropIfExists('periodicidades');
        Schema::dropIfExists('configuraciones');
    }
};
