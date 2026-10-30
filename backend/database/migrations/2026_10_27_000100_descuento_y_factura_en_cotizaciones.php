<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-044: descuento de la cotización (antes de IVA). RF-045: la cotización aprobada puede pasar a
 * facturación; la factura queda vinculada a su cotización de origen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $tabla) {
            $tabla->decimal('descuento', 14, 2)->default(0)->after('subtotal');
            $tabla->foreignId('factura_id')->nullable()->after('orden_id')->constrained('facturas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('factura_id');
            $tabla->dropColumn('descuento');
        });
    }
};
