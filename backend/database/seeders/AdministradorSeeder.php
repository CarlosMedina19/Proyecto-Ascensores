<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdministradorSeeder extends Seeder
{
    public function run(): void
    {
        $rolAdministrador = Rol::where('nombre', 'administrador')->first();

        if (! $rolAdministrador) {
            throw new RuntimeException('No existe el rol "administrador"; ejecuta RolSeeder antes de AdministradorSeeder.');
        }

        $correo = config('admin.correo');

        if (! is_string($correo) || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Configura ADMIN_EMAIL con una dirección de correo válida.');
        }

        if (Usuario::where('correo', $correo)->exists()) {
            return;
        }

        $password = config('admin.password');

        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException(
                'Configura ADMIN_PASSWORD (mínimo 12 caracteres) antes de crear el administrador.'
            );
        }

        Usuario::firstOrCreate(
            ['correo' => $correo],
            [
                'nombre' => 'Administrador',
                'password' => $password,
                'rol_id' => $rolAdministrador->id,
                'activo' => true,
            ]
        );
    }
}
