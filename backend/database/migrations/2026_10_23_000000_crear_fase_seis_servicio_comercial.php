<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Fase 6: certificación anual de cada ascensor, emergencias y atrapamientos 24/7, reporte por QR sin
 * sesión y cotizaciones de correctivos que el cliente aprueba o rechaza.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Código del QR pegado en la cabina: identifica el ascensor sin exponer su id.
        Schema::table('equipos', function (Blueprint $tabla) {
            $tabla->string('codigo_qr', 32)->nullable()->unique();
        });
        DB::table('equipos')->whereNull('codigo_qr')->orderBy('id')->each(function ($equipo) {
            DB::table('equipos')->where('id', $equipo->id)->update(['codigo_qr' => Str::lower(Str::random(20))]);
        });

        // Revisión general anual por un organismo de inspección acreditado (en Bogotá, Acuerdo 470 de 2011 y NTC 5926-1).
        Schema::create('certificaciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $tabla->string('organismo', 150);
            $tabla->string('numero_certificado', 60)->nullable();
            $tabla->string('norma', 80)->default('NTC 5926-1');
            $tabla->date('fecha_inspeccion');
            $tabla->date('fecha_vencimiento');
            $tabla->string('resultado', 20);
            $tabla->text('observaciones')->nullable();
            $tabla->string('archivo_ruta')->nullable();
            $tabla->string('archivo_nombre')->nullable();
            $tabla->foreignId('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamps();
            $tabla->index(['equipo_id', 'fecha_vencimiento']);
        });

        // Emergencias y atrapamientos: desde el panel, la app o el QR de la cabina.
        Schema::create('emergencias', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('numero', 20)->unique();
            $tabla->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $tabla->string('tipo', 20);
            $tabla->string('origen', 10);
            $tabla->boolean('personas_atrapadas')->default(false);
            $tabla->string('reportante_nombre', 120)->nullable();
            $tabla->string('reportante_telefono', 30)->nullable();
            $tabla->text('descripcion')->nullable();
            $tabla->string('estado', 20)->default('reportada');
            $tabla->unsignedSmallInteger('minutos_objetivo');
            $tabla->foreignId('tecnico_id')->nullable()->constrained('tecnicos')->nullOnDelete();
            $tabla->foreignId('orden_id')->nullable()->constrained('ordenes_trabajo')->nullOnDelete();
            $tabla->timestamp('reportada_en');
            $tabla->timestamp('asignada_en')->nullable();
            $tabla->timestamp('llegada_en')->nullable();
            $tabla->timestamp('resuelta_en')->nullable();
            $tabla->text('solucion')->nullable();
            $tabla->foreignId('registrada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamps();
            $tabla->index(['estado', 'reportada_en']);
        });

        Schema::create('seguimientos_emergencia', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('emergencia_id')->constrained('emergencias')->cascadeOnDelete();
            $tabla->string('estado', 20);
            $tabla->text('nota')->nullable();
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamp('created_at')->useCurrent();
        });

        // Cotizaciones de correctivos: el cliente las aprueba o rechaza desde su portal.
        Schema::create('cotizaciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('numero', 20)->unique();
            $tabla->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $tabla->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $tabla->foreignId('orden_id')->nullable()->constrained('ordenes_trabajo')->nullOnDelete();
            $tabla->string('titulo', 150);
            $tabla->text('descripcion')->nullable();
            $tabla->decimal('subtotal', 14, 2)->default(0);
            $tabla->decimal('iva', 14, 2)->default(0);
            $tabla->decimal('total', 14, 2)->default(0);
            $tabla->date('fecha_emision');
            $tabla->date('vigente_hasta');
            $tabla->string('estado', 20)->default('enviada');
            $tabla->timestamp('respondida_en')->nullable();
            $tabla->foreignId('respondida_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->text('motivo_rechazo')->nullable();
            $tabla->foreignId('creada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamps();
        });

        Schema::create('cotizacion_items', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $tabla->string('descripcion', 200);
            $tabla->decimal('cantidad', 10, 2);
            $tabla->decimal('valor_unitario', 14, 2);
            $tabla->decimal('valor_total', 14, 2);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE certificaciones ADD CONSTRAINT certificaciones_resultado_valido CHECK (resultado IN ('conforme', 'condicionado', 'no_conforme'))");
            DB::statement('ALTER TABLE certificaciones ADD CONSTRAINT certificaciones_fechas_validas CHECK (fecha_vencimiento > fecha_inspeccion)');
            DB::statement("ALTER TABLE emergencias ADD CONSTRAINT emergencias_tipo_valido CHECK (tipo IN ('atrapamiento', 'falla', 'ruido', 'otro'))");
            DB::statement("ALTER TABLE emergencias ADD CONSTRAINT emergencias_origen_valido CHECK (origen IN ('panel', 'app', 'qr'))");
            DB::statement("ALTER TABLE emergencias ADD CONSTRAINT emergencias_estado_valido CHECK (estado IN ('reportada', 'asignada', 'en_sitio', 'resuelta', 'cancelada'))");
            DB::statement('ALTER TABLE emergencias ADD CONSTRAINT emergencias_objetivo_valido CHECK (minutos_objetivo BETWEEN 5 AND 1440)');
            DB::statement("ALTER TABLE cotizaciones ADD CONSTRAINT cotizaciones_estado_valido CHECK (estado IN ('enviada', 'aprobada', 'rechazada', 'vencida'))");
            DB::statement('ALTER TABLE cotizaciones ADD CONSTRAINT cotizaciones_valores_validos CHECK (subtotal >= 0 AND iva >= 0 AND total >= 0)');
            DB::statement('ALTER TABLE cotizaciones ADD CONSTRAINT cotizaciones_fechas_validas CHECK (vigente_hasta >= fecha_emision)');
            DB::statement('ALTER TABLE cotizacion_items ADD CONSTRAINT cotizacion_items_valores_validos CHECK (cantidad > 0 AND valor_unitario >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_items');
        Schema::dropIfExists('cotizaciones');
        Schema::dropIfExists('seguimientos_emergencia');
        Schema::dropIfExists('emergencias');
        Schema::dropIfExists('certificaciones');
        Schema::table('equipos', fn (Blueprint $tabla) => $tabla->dropColumn('codigo_qr'));
    }
};
