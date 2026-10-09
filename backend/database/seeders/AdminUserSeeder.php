<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('nombre', 'admin')->first();

        if (! $adminRole) {
            throw new RuntimeException('No existe el rol "admin"; ejecuta RoleSeeder antes de AdminUserSeeder.');
        }

        $email = config('admin.email');

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Configura ADMIN_EMAIL con una dirección de correo válida.');
        }

        if (User::where('correo', $email)->exists()) {
            return;
        }

        $password = config('admin.password');

        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException(
                'Configura ADMIN_PASSWORD (mínimo 12 caracteres) antes de crear el administrador.'
            );
        }

        User::firstOrCreate(
            ['correo' => $email],
            [
                'nombre' => 'Administrador',
                'password' => $password,
                'rol_id' => $adminRole->id,
                'activo' => true,
            ]
        );
    }
}
