<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seeder "seguro": la contraseña NUNCA queda hardcodeada en el código.
     * Se toma de las variables de entorno (.env), con un valor por defecto
     * solo para ambiente local. En producción, ADMIN_PASSWORD debe venir
     * definido en el .env del servidor.
     */
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->first();

        if (! $adminRole) {
            $this->command->warn('No existe el rol "admin". Corre primero el RoleSeeder.');
            return;
        }

        $email = env('ADMIN_EMAIL', 'admin@ascensores.com');
        $password = env('ADMIN_PASSWORD', 'CambiarEsteClave123!');

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrador',
                'password' => $password, // el cast 'hashed' del modelo User lo encripta solo
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );

        if (env('ADMIN_PASSWORD') === null) {
            $this->command->warn('ADMIN_PASSWORD no está definido en .env — se usó una contraseña por defecto SOLO para desarrollo local. Cámbiala en .env antes de producción.');
        }
    }
}
