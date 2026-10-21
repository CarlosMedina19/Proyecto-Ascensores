<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carga y descarga en Excel: código de cliente para relacionar las hojas, puertas de piso por ascensor,
 * personas y técnico asignado del ascensor, y un módulo mínimo de facturas y pagos (cartera).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $tabla) {
            $tabla->string('codigo', 30)->nullable()->unique()->after('identificador_uuid');
        });
        // Código para los clientes existentes: CL-0001, CL-0002…
        DB::table('clientes')->orderBy('id')->each(function ($cliente) {
            DB::table('clientes')->where('id', $cliente->id)->update(['codigo' => sprintf('CL-%04d', $cliente->id)]);
        });

        Schema::table('ascensores', function (Blueprint $tabla) {
            $tabla->unsignedSmallInteger('capacidad_personas')->nullable()->after('capacidad_kg');
        });

        Schema::table('equipos', function (Blueprint $tabla) {
            $tabla->foreignId('tecnico_id')->nullable()->after('edificio_id')->constrained('tecnicos')->nullOnDelete();
        });

        // Puertas de piso: una por parada, con su tipo y el último estado revisado.
        Schema::create('puertas_piso', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $tabla->unsignedSmallInteger('piso');
            $tabla->string('tipo', 20)->default('automatica');
            $tabla->string('estado', 1)->default('P');
            $tabla->string('observacion', 1000)->nullable();
            $tabla->timestamps();
            $tabla->unique(['equipo_id', 'piso']);
        });

        Schema::create('facturas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('cliente_id')->constrained('clientes');
            $tabla->string('numero', 40)->unique();
            $tabla->string('concepto', 500)->nullable();
            $tabla->date('fecha_emision');
            $tabla->date('fecha_vencimiento');
            $tabla->decimal('valor', 14, 2);
            $tabla->decimal('saldo', 14, 2);
            $tabla->string('estado', 20)->default('pendiente');
            $tabla->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamps();
            $tabla->index(['cliente_id', 'estado']);
        });

        Schema::create('pagos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('cliente_id')->constrained('clientes');
            $tabla->foreignId('factura_id')->constrained('facturas');
            $tabla->decimal('valor', 14, 2);
            $tabla->date('fecha');
            $tabla->string('medio', 30);
            $tabla->string('referencia', 100);
            $tabla->foreignId('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamps();
            $tabla->unique(['factura_id', 'referencia']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE puertas_piso ADD CONSTRAINT puertas_piso_tipo_valido CHECK (tipo IN ('automatica', 'manual'))");
            DB::statement("ALTER TABLE puertas_piso ADD CONSTRAINT puertas_piso_estado_valido CHECK (estado IN ('B', 'R', 'M', 'P'))");
            DB::statement('ALTER TABLE puertas_piso ADD CONSTRAINT puertas_piso_piso_valido CHECK (piso BETWEEN 1 AND 300)');
            DB::statement("ALTER TABLE facturas ADD CONSTRAINT facturas_estado_valido CHECK (estado IN ('pendiente', 'parcial', 'pagada'))");
            DB::statement('ALTER TABLE facturas ADD CONSTRAINT facturas_valores_validos CHECK (valor > 0 AND saldo >= 0 AND saldo <= valor)');
            DB::statement('ALTER TABLE facturas ADD CONSTRAINT facturas_fechas_validas CHECK (fecha_vencimiento >= fecha_emision)');
            DB::statement('ALTER TABLE pagos ADD CONSTRAINT pagos_valor_valido CHECK (valor > 0)');
            DB::statement("ALTER TABLE pagos ADD CONSTRAINT pagos_medio_valido CHECK (medio IN ('transferencia', 'consignacion', 'cheque', 'efectivo'))");
        }

        // Puertas de piso de los ascensores existentes, según su número de paradas.
        $ahora = now();
        DB::table('equipos')
            ->join('ascensores', 'ascensores.equipo_id', '=', 'equipos.id')
            ->whereNull('equipos.deleted_at')
            ->select('equipos.id', 'ascensores.paradas', 'ascensores.tipo_puerta')
            ->orderBy('equipos.id')
            ->each(function ($equipo) use ($ahora) {
                $tipo = str_contains(mb_strtolower((string) $equipo->tipo_puerta), 'manual') ? 'manual' : 'automatica';
                $filas = [];
                for ($piso = 1; $piso <= min((int) $equipo->paradas, 300); $piso++) {
                    $filas[] = ['equipo_id' => $equipo->id, 'piso' => $piso, 'tipo' => $tipo, 'estado' => 'P', 'created_at' => $ahora, 'updated_at' => $ahora];
                }
                if ($filas) {
                    DB::table('puertas_piso')->insert($filas);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('facturas');
        Schema::dropIfExists('puertas_piso');
        Schema::table('equipos', fn (Blueprint $tabla) => $tabla->dropConstrainedForeignId('tecnico_id'));
        Schema::table('ascensores', fn (Blueprint $tabla) => $tabla->dropColumn('capacidad_personas'));
        Schema::table('clientes', fn (Blueprint $tabla) => $tabla->dropColumn('codigo'));
    }
};
