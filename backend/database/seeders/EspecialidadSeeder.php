<?php

namespace Database\Seeders;

use App\Models\Especialidad;
use Illuminate\Database\Seeder;

/**
 * RF-022: especialidades iniciales de los técnicos (se pueden ampliar en la base de datos).
 */
class EspecialidadSeeder extends Seeder
{
    public const ESPECIALIDADES = [
        'Mantenimiento preventivo',
        'Mecánica de ascensores',
        'Electricidad y control',
        'Electrónica y variadores',
        'Puertas de cabina y de piso',
        'Sistemas hidráulicos',
        'Inspección y seguridad',
    ];

    public function run(): void
    {
        foreach (self::ESPECIALIDADES as $nombre) {
            Especialidad::firstOrCreate(['nombre' => $nombre], ['activo' => true]);
        }
    }
}
