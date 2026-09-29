<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrador del sistema']);
        Role::firstOrCreate(['name' => 'technician'], ['description' => 'Técnico de mantenimiento']);
        Role::firstOrCreate(['name' => 'client'], ['description' => 'Cliente con acceso al portal']);
    }
}
