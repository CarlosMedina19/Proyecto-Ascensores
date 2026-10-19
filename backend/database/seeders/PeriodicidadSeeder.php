<?php

namespace Database\Seeders;

use App\Models\Periodicidad;
use Illuminate\Database\Seeder;

/**
 * RF-011: periodicidades de 1 a 6 meses. El administrador puede activarlas o desactivarlas
 * (y crear otras) desde la configuración, sin modificar el código.
 */
class PeriodicidadSeeder extends Seeder
{
    public function run(): void
    {
        $periodicidades = [
            1 => 'Mensual',
            2 => 'Bimestral',
            3 => 'Trimestral',
            4 => 'Cuatrimestral',
            5 => 'Cada 5 meses',
            6 => 'Semestral',
        ];

        foreach ($periodicidades as $meses => $nombre) {
            Periodicidad::firstOrCreate(['meses' => $meses], ['nombre' => $nombre, 'activo' => true]);
        }
    }
}
