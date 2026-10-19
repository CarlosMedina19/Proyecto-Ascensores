<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

/**
 * Crea los parámetros configurables con su valor por defecto. No sobrescribe
 * los valores que el administrador ya haya cambiado.
 */
class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Configuracion::PARAMETROS as $clave => $parametro) {
            Configuracion::firstOrCreate(
                ['clave' => $clave],
                ['valor' => $parametro['valor'], 'descripcion' => $parametro['descripcion']]
            );
        }
    }
}
