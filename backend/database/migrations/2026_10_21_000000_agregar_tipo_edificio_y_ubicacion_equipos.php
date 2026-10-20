<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de edificio (conjunto, institucional o residencial) para agrupar los edificios de cada
 * cliente, y ubicación de los ascensores tomada del edificio para evitar datos repetidos o errados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edificios', function (Blueprint $tabla) {
            $tabla->string('tipo', 20)->default('residencial')->after('cliente_id');
            $tabla->index(['cliente_id', 'tipo']);
        });

        // Clasificación inicial de los edificios existentes según el nombre del cliente o del edificio.
        $como = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $porNombre = fn (string $patron) => fn ($consulta) => $consulta
            ->where('nombre', $como, $patron)
            ->orWhereIn('cliente_id', DB::table('clientes')->where('nombre', $como, $patron)->select('id'));

        DB::table('edificios')->where($porNombre('%conjunto%'))->update(['tipo' => 'conjunto']);
        foreach (['%empresarial%', '%institu%', '%universidad%', '%colegio%', '%hospital%', '%clínica%', '%clinica%'] as $patron) {
            DB::table('edificios')->where('tipo', 'residencial')->where($porNombre($patron))->update(['tipo' => 'institucional']);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE edificios ADD CONSTRAINT edificios_tipo_valido CHECK (tipo IN ('conjunto', 'institucional', 'residencial'))");
        }

        // La ubicación del ascensor es la del edificio.
        DB::table('equipos')->orderBy('id')->each(function ($equipo) {
            $edificio = DB::table('edificios')->where('id', $equipo->edificio_id)->first();
            if ($edificio) {
                DB::table('equipos')->where('id', $equipo->id)->update([
                    'ubicacion' => mb_substr("{$edificio->nombre} · {$edificio->direccion}, {$edificio->ciudad}", 0, 255),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE edificios DROP CONSTRAINT IF EXISTS edificios_tipo_valido');
        }

        Schema::table('edificios', function (Blueprint $tabla) {
            $tabla->dropIndex(['cliente_id', 'tipo']);
            $tabla->dropColumn('tipo');
        });
    }
};
