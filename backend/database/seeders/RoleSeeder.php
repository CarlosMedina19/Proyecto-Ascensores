<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin',       'description' => 'Administrador del sistema'],
            ['name' => 'coordinador', 'description' => 'Coordinador de operaciones'],
            ['name' => 'tecnico',  'description' => 'Técnico de mantenimiento'],
            ['name' => 'cliente',      'description' => 'Cliente con acceso al portal'],
            ['name' => 'contador',  'description' => 'Contador / Cartera'],
            ['name' => 'supervisor',  'description' => 'Supervisor de técnicos'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }
    }
}