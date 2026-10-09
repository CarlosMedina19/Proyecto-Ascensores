<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class PermisoSeeder extends Seeder
{
    private const PERMISSIONS = [
        'resumen.ver' => 'Ver el resumen del sistema',
        'roles.ver' => 'Ver roles y sus permisos',
        'roles.crear' => 'Crear roles',
        'roles.editar' => 'Actualizar roles',
        'roles.permisos.editar' => 'Asignar permisos a roles',
        'permisos.ver' => 'Consultar el catálogo de permisos',
        'usuarios.ver' => 'Consultar usuarios',
        'usuarios.crear' => 'Crear usuarios',
        'usuarios.editar' => 'Actualizar y desactivar usuarios',
        'registros_auditoria.ver' => 'Consultar la bitácora de auditoría',
        'clientes.ver' => 'Consultar clientes y contactos',
        'clientes.crear' => 'Crear clientes',
        'clientes.editar' => 'Actualizar clientes',
        'clientes.eliminar' => 'Desactivar clientes',
        'clientes.contactos.crear' => 'Agregar contactos a clientes',
        'edificios.ver' => 'Consultar edificios',
        'edificios.crear' => 'Crear edificios',
        'edificios.editar' => 'Actualizar edificios',
        'edificios.eliminar' => 'Desactivar edificios',
        'equipos.ver' => 'Consultar equipos y ascensores',
        'equipos.crear' => 'Registrar equipos y ascensores',
        'equipos.editar' => 'Actualizar equipos y ascensores',
        'equipos.eliminar' => 'Dar de baja equipos',
        'equipos.historial.ver' => 'Consultar el historial de equipos',
        'equipos.historial.crear' => 'Registrar eventos en el historial de equipos',
    ];

    public function run(): void
    {
        $permisos = [];

        foreach (self::PERMISSIONS as $name => $description) {
            $permisos[$name] = Permiso::firstOrCreate(
                ['nombre' => $name],
                ['descripcion' => $description]
            );
        }

        $permisosCatalogo = collect(array_keys(self::PERMISSIONS))
            ->filter(fn (string $nombre): bool => str_starts_with($nombre, 'clientes.')
                || str_starts_with($nombre, 'edificios.')
                || str_starts_with($nombre, 'equipos.'))
            ->all();

        $rolePermissions = [
            'administrador' => array_keys(self::PERMISSIONS),
            'coordinador' => array_merge([
                'resumen.ver',
                'roles.ver',
                'usuarios.ver',
                'usuarios.crear',
                'usuarios.editar',
                'registros_auditoria.ver',
            ], $permisosCatalogo),
            'supervisor' => [
                'resumen.ver',
                'usuarios.ver',
                'roles.ver',
                'registros_auditoria.ver',
                'clientes.ver',
                'edificios.ver',
                'equipos.ver',
                'equipos.historial.ver',
            ],
            'cliente' => [
                'clientes.ver',
                'edificios.ver',
                'equipos.ver',
                'equipos.historial.ver',
            ],
        ];

        foreach ($rolePermissions as $nombreRol => $nombresPermisos) {
            $rol = Rol::where('nombre', $nombreRol)->first();

            if ($rol) {
                $rol->permisos()->syncWithoutDetaching(
                    collect($nombresPermisos)->map(fn (string $nombre): int => $permisos[$nombre]->id)->all()
                );
            }
        }
    }
}
