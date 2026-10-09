<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'dashboard.view' => 'Ver el resumen del sistema',
        'roles.view' => 'Ver roles y sus permisos',
        'users.view' => 'Consultar usuarios',
        'users.create' => 'Crear usuarios',
        'users.update' => 'Actualizar y desactivar usuarios',
        'audit-logs.view' => 'Consultar la bitácora de auditoría',
        'clients.view' => 'Consultar clientes y contactos',
        'clients.create' => 'Crear clientes',
        'clients.update' => 'Actualizar clientes',
        'clients.delete' => 'Desactivar clientes',
        'clients.contacts.create' => 'Agregar contactos a clientes',
        'buildings.view' => 'Consultar edificios',
        'buildings.create' => 'Crear edificios',
        'buildings.update' => 'Actualizar edificios',
        'buildings.delete' => 'Desactivar edificios',
        'equipment.view' => 'Consultar equipos y ascensores',
        'equipment.create' => 'Registrar equipos y ascensores',
        'equipment.update' => 'Actualizar equipos y ascensores',
        'equipment.delete' => 'Dar de baja equipos',
        'equipment.history.view' => 'Consultar el historial de equipos',
        'equipment.history.create' => 'Registrar eventos en el historial de equipos',
    ];

    public function run(): void
    {
        $permissions = [];

        foreach (self::PERMISSIONS as $name => $description) {
            $permissions[$name] = Permission::firstOrCreate(
                ['nombre' => $name],
                ['descripcion' => $description]
            );
        }

        $catalogPermissions = collect(array_keys(self::PERMISSIONS))
            ->filter(fn (string $name): bool => str_starts_with($name, 'clients.')
                || str_starts_with($name, 'buildings.')
                || str_starts_with($name, 'equipment.'))
            ->all();

        $rolePermissions = [
            'admin' => array_keys(self::PERMISSIONS),
            'coordinador' => array_merge([
                'dashboard.view',
                'roles.view',
                'users.view',
                'users.create',
                'users.update',
                'audit-logs.view',
            ], $catalogPermissions),
            'supervisor' => [
                'dashboard.view',
                'users.view',
                'roles.view',
                'audit-logs.view',
                'clients.view',
                'buildings.view',
                'equipment.view',
                'equipment.history.view',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::where('nombre', $roleName)->first();

            if ($role) {
                $role->permissions()->syncWithoutDetaching(
                    collect($permissionNames)->map(fn (string $name): int => $permissions[$name]->id)->all()
                );
            }
        }
    }
}
