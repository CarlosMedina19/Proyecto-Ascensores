<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RF-040 a RF-043: inventario de repuestos, movimientos (entradas, salidas, ajustes, devoluciones y
 * consumos de órdenes) y repuestos usados en cada orden, que se descuentan al finalizarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repuestos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('codigo', 40)->unique();
            $tabla->string('nombre', 150);
            $tabla->string('marca', 100)->nullable();
            $tabla->string('referencia', 100)->nullable();
            $tabla->string('unidad', 20)->default('unidad');
            $tabla->decimal('precio', 14, 2)->default(0);
            $tabla->decimal('stock', 12, 2)->default(0);
            $tabla->decimal('stock_minimo', 12, 2)->default(0);
            $tabla->boolean('activo')->default(true);
            // RF-043: la alerta de stock mínimo no se repite hasta que se reponga el stock.
            $tabla->timestampTz('alerta_stock_en')->nullable();
            $tabla->text('observaciones')->nullable();
            $tabla->timestamps();
            $tabla->index(['activo', 'nombre']);
        });

        Schema::create('movimientos_inventario', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('repuesto_id')->constrained('repuestos')->restrictOnDelete();
            $tabla->string('tipo', 20); // entrada, salida, ajuste, devolucion, consumo
            $tabla->decimal('cantidad', 12, 2); // siempre positiva
            $tabla->decimal('variacion', 12, 2); // con signo: lo que cambió el stock
            $tabla->decimal('stock_resultante', 12, 2);
            $tabla->string('motivo', 500);
            $tabla->foreignId('orden_id')->nullable()->constrained('ordenes_trabajo')->nullOnDelete();
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampTz('created_at')->useCurrent();
            $tabla->index(['repuesto_id', 'created_at']);
        });

        Schema::create('orden_repuestos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('orden_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $tabla->foreignId('repuesto_id')->constrained('repuestos')->restrictOnDelete();
            $tabla->decimal('cantidad', 12, 2);
            // RF-042: lo que se descontó al finalizar y lo que faltó (queda para reposición).
            $tabla->decimal('cantidad_descontada', 12, 2)->default(0);
            $tabla->decimal('faltante', 12, 2)->default(0);
            $tabla->foreignId('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestamps();
            $tabla->unique(['orden_id', 'repuesto_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE repuestos ADD CONSTRAINT repuestos_valores_no_negativos CHECK (precio >= 0 AND stock >= 0 AND stock_minimo >= 0)');
            DB::statement('ALTER TABLE movimientos_inventario ADD CONSTRAINT movimientos_cantidad_positiva CHECK (cantidad > 0 AND stock_resultante >= 0)');
            DB::statement('ALTER TABLE orden_repuestos ADD CONSTRAINT orden_repuestos_cantidad_positiva CHECK (cantidad > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_repuestos');
        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('repuestos');
    }
};
