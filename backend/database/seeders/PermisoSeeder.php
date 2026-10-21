<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class PermisoSeeder extends Seeder
{
    private const PERMISOS = [
        'resumen.ver' => 'Ver el resumen del sistema',
        'roles.ver' => 'Ver roles y sus permisos',
        'roles.crear' => 'Crear roles',
        'roles.editar' => 'Actualizar roles',
        'roles.permisos.editar' => 'Asignar permisos a roles',
        'permisos.ver' => 'Consultar el catálogo de permisos',
        'usuarios.ver' => 'Consultar usuarios',
        'usuarios.crear' => 'Crear usuarios',
        'usuarios.editar' => 'Actualizar, activar y desactivar usuarios',
        'registros_auditoria.ver' => 'Consultar la auditoría',
        'clientes.ver' => 'Consultar clientes y contactos',
        'clientes.crear' => 'Crear clientes',
        'clientes.editar' => 'Actualizar clientes',
        'clientes.eliminar' => 'Eliminar clientes sin edificios',
        'clientes.contactos.crear' => 'Agregar contactos a clientes',
        'edificios.ver' => 'Consultar edificios',
        'edificios.crear' => 'Crear edificios',
        'edificios.editar' => 'Actualizar y desactivar edificios',
        'edificios.eliminar' => 'Eliminar edificios sin equipos activos',
        'equipos.ver' => 'Consultar equipos y ascensores',
        'equipos.crear' => 'Registrar equipos y ascensores',
        'equipos.editar' => 'Actualizar equipos y ascensores',
        'equipos.eliminar' => 'Dar de baja equipos',
        'equipos.historial.ver' => 'Consultar el historial de equipos',
        'equipos.historial.crear' => 'Registrar eventos en el historial de equipos',
        // Fase 2: planes, precios, contratos y programación.
        'planes.ver' => 'Consultar el catálogo de planes',
        'planes.crear' => 'Crear planes de mantenimiento',
        'planes.editar' => 'Actualizar y desactivar planes',
        'planes.eliminar' => 'Eliminar planes sin asignaciones activas',
        'precios.editar' => 'Cambiar precios de planes y asignaciones',
        'asignaciones.ver' => 'Consultar los planes asignados a los ascensores',
        'asignaciones.crear' => 'Asignar planes a ascensores',
        'asignaciones.editar' => 'Cambiar plan, periodicidad o vigencia de una asignación',
        'mantenimientos.ver' => 'Consultar la programación de mantenimientos',
        'mantenimientos.gestionar' => 'Reprogramar, cancelar y registrar mantenimientos',
        'contratos.ver' => 'Consultar contratos',
        'contratos.crear' => 'Crear contratos',
        'contratos.editar' => 'Actualizar y cancelar contratos',
        'contratos.renovacion' => 'Hacer seguimiento y renovar contratos',
        'configuracion.editar' => 'Configurar periodicidades, avisos e IVA',
        // Fase 3: técnicos, agenda y órdenes de trabajo.
        'tecnicos.ver' => 'Consultar técnicos, especialidades y disponibilidad',
        'tecnicos.crear' => 'Registrar técnicos (crea su cuenta de usuario)',
        'tecnicos.editar' => 'Actualizar especialidades, disponibilidad y estado de los técnicos',
        'ordenes.ver' => 'Consultar órdenes de trabajo y la agenda',
        'ordenes.crear' => 'Crear órdenes de trabajo',
        'ordenes.editar' => 'Reprogramar órdenes y cambiar su prioridad',
        'ordenes.asignar' => 'Asignar técnicos a las órdenes',
        'ordenes.cancelar' => 'Cancelar órdenes con motivo',
        'ordenes.ejecutar' => 'Avanzar los estados de las órdenes asignadas (en camino, en proceso, pendiente, finalizada)',
        // Cartera (facturas y pagos) y carga de datos desde Excel.
        'cartera.ver' => 'Consultar facturas, pagos y saldos de cartera',
        'facturas.crear' => 'Registrar facturas',
        'pagos.registrar' => 'Registrar pagos de los clientes',
        'datos.importar' => 'Importar datos desde la plantilla de Excel',
    ];

    public function run(): void
    {
        $permisos = [];

        foreach (self::PERMISOS as $nombre => $descripcion) {
            $permisos[$nombre] = Permiso::updateOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => $descripcion]
            );
        }

        $permisosCatalogo = collect(array_keys(self::PERMISOS))
            ->filter(fn (string $nombre): bool => str_starts_with($nombre, 'clientes.')
                || str_starts_with($nombre, 'edificios.')
                || str_starts_with($nombre, 'equipos.'))
            ->all();

        // RF-002: permisos iniciales por rol.
        $permisosPlanes = [
            'planes.ver', 'planes.crear', 'planes.editar', 'planes.eliminar',
            'asignaciones.ver', 'asignaciones.crear', 'asignaciones.editar',
            'mantenimientos.ver', 'mantenimientos.gestionar',
            'contratos.ver', 'contratos.crear', 'contratos.editar', 'contratos.renovacion',
            'tecnicos.ver', 'tecnicos.editar',
            'ordenes.ver', 'ordenes.crear', 'ordenes.editar', 'ordenes.asignar', 'ordenes.cancelar',
        ];

        $permisosPorRol = [
            'administrador' => array_keys(self::PERMISOS),
            'coordinador' => array_merge(['resumen.ver', 'roles.ver', 'usuarios.ver', 'cartera.ver', 'datos.importar'], $permisosCatalogo, $permisosPlanes),
            // El supervisor solo consulta; no modifica datos.
            'supervisor' => [
                'resumen.ver',
                'clientes.ver',
                'edificios.ver',
                'equipos.ver',
                'equipos.historial.ver',
                'planes.ver',
                'asignaciones.ver',
                'mantenimientos.ver',
                'contratos.ver',
                'tecnicos.ver',
                'ordenes.ver',
            ],
            'tecnico' => [
                'edificios.ver',
                'equipos.ver',
                'equipos.historial.ver',
                'equipos.historial.crear',
                'asignaciones.ver',
                'mantenimientos.ver',
                // RF-023: el técnico ve solo sus órdenes y avanza sus estados.
                'ordenes.ver',
                'ordenes.ejecutar',
            ],
            // RF-015: solo el contador y el administrador cambian precios.
            'contador' => [
                'resumen.ver',
                'clientes.ver',
                'edificios.ver',
                'equipos.ver',
                'planes.ver',
                'precios.editar',
                'asignaciones.ver',
                'mantenimientos.ver',
                'contratos.ver',
                'contratos.crear',
                'contratos.editar',
                'contratos.renovacion',
                'ordenes.ver',
                'cartera.ver',
                'facturas.crear',
                'pagos.registrar',
                'datos.importar',
            ],
            // El cliente consulta solo sus propios datos (las políticas filtran por cliente).
            'cliente' => [
                'clientes.ver',
                'edificios.ver',
                'equipos.ver',
                'equipos.historial.ver',
                'asignaciones.ver',
                'mantenimientos.ver',
                'contratos.ver',
                'ordenes.ver',
                'cartera.ver',
            ],
        ];

        $exclusivos = collect(config('sigma.permisos_exclusivos_administrador'))
            ->map(fn (string $nombre): int => $permisos[$nombre]->id)
            ->all();

        foreach ($permisosPorRol as $nombreRol => $nombresPermisos) {
            $rol = Rol::where('nombre', $nombreRol)->first();

            if (! $rol) {
                continue;
            }

            // Conserva permisos agregados por el administrador, pero agrega los iniciales.
            $rol->permisos()->syncWithoutDetaching(
                collect($nombresPermisos)->map(fn (string $nombre): int => $permisos[$nombre]->id)->all()
            );
        }

        // RF-002/RF-003/RF-004: retira los permisos exclusivos de cualquier rol que no sea administrador.
        Rol::where('nombre', '!=', 'administrador')->each(
            fn (Rol $rol) => $rol->permisos()->detach($exclusivos)
        );
    }
}
