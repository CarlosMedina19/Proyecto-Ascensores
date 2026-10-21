<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nombre corto del ascensor dentro de su edificio (por ejemplo, "Ascensor 1" o "Montacamillas"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipos', function (Blueprint $tabla) {
            $tabla->string('nombre', 100)->nullable()->after('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('equipos', fn (Blueprint $tabla) => $tabla->dropColumn('nombre'));
    }
};
