<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los tipos de ascensor pasan a ser un catálogo que el administrador edita en Configuración.
 * ascensores.tipo_ascensor sigue guardando la clave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_ascensor', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('clave', 60)->unique();
            $tabla->string('nombre', 100)->unique();
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
        });

        $iniciales = [
            'pasajeros' => 'Pasajeros',
            'carga' => 'Carga (montacargas)',
            'camillero' => 'Camillero (hospitalario)',
            'panoramico' => 'Panorámico',
            'vehicular' => 'Vehicular',
            'plataforma' => 'Plataforma para movilidad reducida',
            'montaplatos' => 'Montaplatos',
        ];
        foreach ($iniciales as $clave => $nombre) {
            DB::table('tipos_ascensor')->insert(['clave' => $clave, 'nombre' => $nombre, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_ascensor');
    }
};
