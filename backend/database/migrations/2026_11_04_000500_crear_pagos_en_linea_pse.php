<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos en línea por PSE (pasarela Wompi, en modo de pruebas). Cada intento tiene una referencia única;
 * cuando Wompi lo aprueba se registra el pago en la factura con el medio "pse".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_en_linea', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('factura_id')->constrained('facturas');
            $tabla->string('referencia', 60)->unique();
            $tabla->unsignedBigInteger('monto_centavos');
            $tabla->string('estado', 20)->default('pendiente');
            $tabla->string('transaccion_id', 80)->nullable()->unique();
            $tabla->string('medio', 30)->nullable();
            $tabla->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->json('respuesta')->nullable();
            $tabla->timestamps();
            $tabla->index(['factura_id', 'estado']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pagos DROP CONSTRAINT IF EXISTS pagos_medio_valido');
            DB::statement("ALTER TABLE pagos ADD CONSTRAINT pagos_medio_valido CHECK (medio IN ('transferencia', 'consignacion', 'cheque', 'efectivo', 'pse'))");
            DB::statement("ALTER TABLE pagos_en_linea ADD CONSTRAINT pagos_en_linea_estado CHECK (estado IN ('pendiente', 'aprobado', 'rechazado', 'anulado', 'error'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_en_linea');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pagos DROP CONSTRAINT IF EXISTS pagos_medio_valido');
            DB::statement("ALTER TABLE pagos ADD CONSTRAINT pagos_medio_valido CHECK (medio IN ('transferencia', 'consignacion', 'cheque', 'efectivo'))");
        }
    }
};
