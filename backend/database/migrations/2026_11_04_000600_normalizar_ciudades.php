<?php

use App\Support\Colombia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ciudades y departamentos ya guardados con su nombre oficial ("ibague", "tolima" → "Ibagué", "Tolima";
 * Bogotá pasa a Bogotá D.C.). Lo que no se reconoce queda como estaba.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['conjuntos', 'edificios'] as $tabla) {
            foreach (DB::table($tabla)->select('id', 'ciudad', 'departamento')->get() as $fila) {
                $ubicacion = Colombia::ubicacion($fila->ciudad, $fila->departamento) ?? Colombia::ubicacion($fila->ciudad, null);
                if ($ubicacion && [$fila->ciudad, $fila->departamento] !== $ubicacion) {
                    DB::table($tabla)->where('id', $fila->id)->update(['ciudad' => $ubicacion[0], 'departamento' => $ubicacion[1]]);
                }
            }
        }
    }

    public function down(): void
    {
        // Los nombres anteriores no se restauran: eran variantes del mismo lugar.
    }
};
