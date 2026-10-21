<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los usuarios se pueden eliminar desde la tabla: la eliminación es lógica para que órdenes, historial y
 * auditoría conserven quién hizo cada cosa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $tabla) {
            $tabla->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $tabla) {
            $tabla->dropSoftDeletes();
        });
    }
};
