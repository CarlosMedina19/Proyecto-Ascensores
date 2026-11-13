<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolSeeder::class,
            PermisoSeeder::class,
            PeriodicidadSeeder::class,
            ConfiguracionSeeder::class,
            EspecialidadSeeder::class,
            // Ítems de la planilla de mantenimiento: sin ellos la planilla de la orden queda vacía.
            ChecklistSeeder::class,
            AdministradorSeeder::class,
        ]);
    }
}
