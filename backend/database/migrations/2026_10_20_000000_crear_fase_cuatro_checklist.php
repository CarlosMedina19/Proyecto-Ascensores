<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4: planilla digital de mantenimiento preventivo (RF-030 a RF-037), correctivo derivado
 * (RF-038), firmas e informe PDF (RF-039).
 */
return new class extends Migration
{
    public function up(): void
    {
        // RF-031 a RF-036: ítems de cada sección del ascensor (configurables).
        Schema::create('checklist_items', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('seccion', 30);
            $tabla->string('codigo', 20)->unique();
            $tabla->string('nombre', 150);
            $tabla->unsignedSmallInteger('orden')->default(1);
            // Ítems de seguridad o emergencia: en estado M se avisa de inmediato al coordinador.
            $tabla->boolean('critico')->default(false);
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();
            $tabla->index(['seccion', 'orden']);
        });

        // RF-030: una planilla por orden preventiva.
        Schema::create('planillas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('orden_id')->unique()->constrained('ordenes_trabajo')->cascadeOnDelete();
            $tabla->foreignId('tecnico_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->text('recomendaciones')->nullable();
            $tabla->timestampTz('iniciada_en')->useCurrent();
            $tabla->timestampTz('completada_en')->nullable();
            $tabla->timestampsTz();
        });

        // RF-037: resultado (INS, EST, NA) y estado (B, R, M) de cada ítem.
        Schema::create('planilla_resultados', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('planilla_id')->constrained('planillas')->cascadeOnDelete();
            $tabla->foreignId('item_id')->constrained('checklist_items')->restrictOnDelete();
            $tabla->string('resultado', 3);
            $tabla->char('estado', 1);
            $tabla->text('observacion')->nullable();
            $tabla->timestampTz('notificado_en')->nullable();
            $tabla->foreignId('actualizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampsTz();
            $tabla->unique(['planilla_id', 'item_id']);
        });

        Schema::create('planilla_fotos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('planilla_id')->constrained('planillas')->cascadeOnDelete();
            $tabla->foreignId('item_id')->nullable()->constrained('checklist_items')->nullOnDelete();
            $tabla->string('ruta');
            $tabla->string('nombre_original');
            $tabla->string('tipo_mime', 50);
            $tabla->unsignedInteger('tamano');
            $tabla->string('descripcion')->nullable();
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampsTz();
        });

        // RF-025 y RF-039: firmas del técnico y de quien recibe el servicio.
        Schema::create('firmas_orden', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('orden_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $tabla->string('tipo', 10);
            $tabla->string('nombre', 150);
            $tabla->string('documento', 30)->nullable();
            $tabla->string('cargo', 100)->nullable();
            $tabla->string('ruta');
            $tabla->foreignId('registrada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampTz('firmado_en')->useCurrent();
            $tabla->timestampsTz();
            $tabla->unique(['orden_id', 'tipo']);
        });

        // RF-039: un informe emitido no se modifica; las correcciones son versiones nuevas.
        Schema::create('informes_orden', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('orden_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $tabla->unsignedSmallInteger('version');
            $tabla->string('ruta');
            $tabla->string('hash', 64);
            $tabla->unsignedInteger('tamano');
            $tabla->text('motivo')->nullable();
            $tabla->foreignId('generado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampTz('created_at')->useCurrent();
            $tabla->unique(['orden_id', 'version']);
        });

        // RF-038: el correctivo derivado queda vinculado a la orden y al ítem de origen.
        Schema::table('ordenes_trabajo', function (Blueprint $tabla) {
            $tabla->foreignId('orden_origen_id')->nullable()->constrained('ordenes_trabajo')->nullOnDelete();
            $tabla->foreignId('resultado_origen_id')->nullable()->constrained('planilla_resultados')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach ([
                "ALTER TABLE checklist_items ADD CONSTRAINT checklist_seccion_check CHECK (seccion IN ('cuarto_maquinas', 'cabina', 'contrapeso', 'puertas', 'hueco', 'foso'))",
                "ALTER TABLE planilla_resultados ADD CONSTRAINT resultados_resultado_check CHECK (resultado IN ('INS', 'EST', 'NA'))",
                "ALTER TABLE planilla_resultados ADD CONSTRAINT resultados_estado_check CHECK (estado IN ('B', 'R', 'M'))",
                // RF-037: R o M exige observación.
                "ALTER TABLE planilla_resultados ADD CONSTRAINT resultados_observacion_check CHECK (estado = 'B' OR length(trim(coalesce(observacion, ''))) > 0)",
                "ALTER TABLE firmas_orden ADD CONSTRAINT firmas_tipo_check CHECK (tipo IN ('tecnico', 'cliente'))",
                // RF-038: no hay dos correctivos abiertos para el mismo ítem.
                "CREATE UNIQUE INDEX ordenes_un_correctivo_abierto_por_item ON ordenes_trabajo (resultado_origen_id) WHERE resultado_origen_id IS NOT NULL AND estado NOT IN ('finalizada', 'cancelada')",
            ] as $sentencia) {
                DB::statement($sentencia);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('resultado_origen_id');
            $tabla->dropConstrainedForeignId('orden_origen_id');
        });
        Schema::dropIfExists('informes_orden');
        Schema::dropIfExists('firmas_orden');
        Schema::dropIfExists('planilla_fotos');
        Schema::dropIfExists('planilla_resultados');
        Schema::dropIfExists('planillas');
        Schema::dropIfExists('checklist_items');
    }
};
