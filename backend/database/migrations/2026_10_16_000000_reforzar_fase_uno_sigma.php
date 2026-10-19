<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajusta el esquema de la fase 1 a los requerimientos de SIGMA:
 * - RF-001: bloqueo por intentos fallidos y recuperación de contraseña.
 * - RF-004: auditoría con módulo e IP, fechas TIMESTAMPTZ.
 * - RF-005: documento único por cliente.
 * - RF-006: los edificios se desactivan en lugar de eliminarse.
 * - RF-008: puertas de cabina y de piso dentro del ascensor; valores mayores que cero.
 * - RNF-04 / RNF-08: TIMESTAMPTZ para eventos e índices en llaves foráneas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $tabla) {
                $tabla->string('email')->primary();
                $tabla->string('token');
                $tabla->timestamp('created_at')->nullable();
            });
        }

        Schema::table('usuarios', function (Blueprint $tabla) {
            $tabla->unsignedSmallInteger('intentos_fallidos')->default(0);
            $tabla->timestampTz('bloqueado_hasta')->nullable();
            $tabla->timestampTz('ultimo_acceso_en')->nullable();
            $tabla->index('rol_id');
            $tabla->index('cliente_id');
        });

        Schema::table('registros_auditoria', function (Blueprint $tabla) {
            $tabla->string('modulo', 60)->nullable()->after('accion');
            $tabla->string('ip', 45)->nullable()->after('cambios');
            $tabla->timestampTz('created_at')->useCurrent()->change();
            $tabla->index('created_at');
            $tabla->index('usuario_id');
            $tabla->index('modulo');
            $tabla->index(['modelo', 'modelo_id']);
        });

        Schema::table('historial_equipos', function (Blueprint $tabla) {
            $tabla->text('descripcion')->nullable()->change();
            $tabla->timestampTz('created_at')->useCurrent()->change();
            $tabla->index(['equipo_id', 'created_at']);
        });

        Schema::table('clientes', function (Blueprint $tabla) {
            $tabla->unique('numero_documento');
            $tabla->index('estado');
        });

        Schema::table('contactos_cliente', function (Blueprint $tabla) {
            $tabla->index('cliente_id');
        });

        Schema::table('edificios', function (Blueprint $tabla) {
            $tabla->boolean('activo')->default(true);
            $tabla->index('cliente_id');
        });

        Schema::table('equipos', function (Blueprint $tabla) {
            $tabla->string('estado')->default('activo')->change();
            $tabla->index('edificio_id');
            $tabla->index('estado');
        });

        Schema::table('ascensores', function (Blueprint $tabla) {
            $tabla->unsignedSmallInteger('puertas_cabina')->nullable();
            $tabla->unsignedSmallInteger('puertas_piso')->nullable();
            $tabla->string('operador_puertas')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ascensores ADD CONSTRAINT ascensores_capacidad_kg_check CHECK (capacidad_kg IS NULL OR capacidad_kg > 0)');
            DB::statement('ALTER TABLE ascensores ADD CONSTRAINT ascensores_velocidad_mpm_check CHECK (velocidad_mpm IS NULL OR velocidad_mpm > 0)');
            DB::statement('ALTER TABLE ascensores ADD CONSTRAINT ascensores_paradas_check CHECK (paradas IS NULL OR paradas > 0)');
            DB::statement('ALTER TABLE usuarios ADD CONSTRAINT usuarios_intentos_fallidos_check CHECK (intentos_fallidos >= 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ascensores DROP CONSTRAINT IF EXISTS ascensores_capacidad_kg_check');
            DB::statement('ALTER TABLE ascensores DROP CONSTRAINT IF EXISTS ascensores_velocidad_mpm_check');
            DB::statement('ALTER TABLE ascensores DROP CONSTRAINT IF EXISTS ascensores_paradas_check');
            DB::statement('ALTER TABLE usuarios DROP CONSTRAINT IF EXISTS usuarios_intentos_fallidos_check');
        }

        Schema::table('ascensores', function (Blueprint $tabla) {
            $tabla->dropColumn(['puertas_cabina', 'puertas_piso', 'operador_puertas']);
        });

        Schema::table('equipos', function (Blueprint $tabla) {
            $tabla->dropIndex(['edificio_id']);
            $tabla->dropIndex(['estado']);
        });

        Schema::table('edificios', function (Blueprint $tabla) {
            $tabla->dropIndex(['cliente_id']);
            $tabla->dropColumn('activo');
        });

        Schema::table('contactos_cliente', function (Blueprint $tabla) {
            $tabla->dropIndex(['cliente_id']);
        });

        Schema::table('clientes', function (Blueprint $tabla) {
            $tabla->dropUnique(['numero_documento']);
            $tabla->dropIndex(['estado']);
        });

        Schema::table('historial_equipos', function (Blueprint $tabla) {
            $tabla->dropIndex(['equipo_id', 'created_at']);
            $tabla->timestamp('created_at')->useCurrent()->change();
        });

        Schema::table('registros_auditoria', function (Blueprint $tabla) {
            $tabla->dropIndex(['created_at']);
            $tabla->dropIndex(['usuario_id']);
            $tabla->dropIndex(['modulo']);
            $tabla->dropIndex(['modelo', 'modelo_id']);
            $tabla->dropColumn(['modulo', 'ip']);
            $tabla->timestamp('created_at')->useCurrent()->change();
        });

        Schema::table('usuarios', function (Blueprint $tabla) {
            $tabla->dropIndex(['rol_id']);
            $tabla->dropIndex(['cliente_id']);
            $tabla->dropColumn(['intentos_fallidos', 'bloqueado_hasta', 'ultimo_acceso_en']);
        });
    }
};
