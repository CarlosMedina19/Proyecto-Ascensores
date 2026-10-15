<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['nombre' => 'administrador',       'descripcion' => 'Administrador del sistema'],
            ['nombre' => 'coordinador', 'descripcion' => 'Coordinador de operaciones'],
            ['nombre' => 'tecnico',  'descripcion' => 'Técnico de mantenimiento'],
            ['nombre' => 'cliente',      'descripcion' => 'Cliente con acceso al portal'],
            ['nombre' => 'contador',  'descripcion' => 'Contador / Cartera'],
            ['nombre' => 'supervisor',  'descripcion' => 'Supervisor de técnicos'],
        ];

        foreach ($roles as $rol) {
            Rol::firstOrCreate(
                ['nombre' => $rol['nombre']],
                ['descripcion' => $rol['descripcion']]
            );
        }
    }
}
