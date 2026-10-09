<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = [
        'users' => [
            'name' => 'nombre',
            'email' => 'correo',
            'role_id' => 'rol_id',
            'is_active' => 'activo',
        ],
        'roles' => [
            'name' => 'nombre',
            'description' => 'descripcion',
        ],
        'permissions' => [
            'name' => 'nombre',
            'description' => 'descripcion',
        ],
        'role_permissions' => [
            'role_id' => 'rol_id',
            'permission_id' => 'permiso_id',
        ],
        'audit_logs' => [
            'user_id' => 'usuario_id',
            'action' => 'accion',
            'model' => 'modelo',
            'model_id' => 'modelo_id',
            'changes' => 'cambios',
        ],
        'clients' => [
            'uuid' => 'identificador_uuid',
            'name' => 'nombre',
            'type' => 'tipo',
            'document_type' => 'tipo_documento',
            'document_number' => 'numero_documento',
            'address' => 'direccion',
            'phone' => 'telefono',
            'email' => 'correo',
            'tax_regime' => 'regimen_tributario',
            'economic_activity' => 'actividad_economica',
            'status' => 'estado',
            'observations' => 'observaciones',
        ],
        'client_contacts' => [
            'client_id' => 'cliente_id',
            'name' => 'nombre',
            'position' => 'cargo',
            'phone' => 'telefono',
            'email' => 'correo',
        ],
        'buildings' => [
            'uuid' => 'identificador_uuid',
            'client_id' => 'cliente_id',
            'name' => 'nombre',
            'address' => 'direccion',
            'city' => 'ciudad',
            'department' => 'departamento',
            'postal_code' => 'codigo_postal',
            'floors' => 'pisos',
            'observations' => 'observaciones',
            'contact_name' => 'nombre_contacto',
            'contact_phone' => 'telefono_contacto',
            'contact_email' => 'correo_contacto',
        ],
        'equipment' => [
            'uuid' => 'identificador_uuid',
            'building_id' => 'edificio_id',
            'code' => 'codigo',
            'type' => 'tipo',
            'brand' => 'marca',
            'model' => 'modelo',
            'serial_number' => 'numero_serie',
            'location' => 'ubicacion',
            'status' => 'estado',
            'installation_date' => 'fecha_instalacion',
            'observations' => 'observaciones',
        ],
        'elevators' => [
            'equipment_id' => 'equipo_id',
            'brand' => 'marca',
            'model' => 'modelo',
            'capacity_kg' => 'capacidad_kg',
            'stops' => 'paradas',
            'speed_mpm' => 'velocidad_mpm',
            'drive_type' => 'tipo_traccion',
            'controller' => 'controlador',
            'door_type' => 'tipo_puerta',
            'technical_specs' => 'especificaciones_tecnicas',
            'installation_date' => 'fecha_instalacion',
        ],
        'electric_doors' => [
            'equipment_id' => 'equipo_id',
            'brand' => 'marca',
            'model' => 'modelo',
            'door_type' => 'tipo_puerta',
            'installation_date' => 'fecha_instalacion',
            'opening_type' => 'tipo_apertura',
            'access_type' => 'tipo_acceso',
            'serial_number' => 'numero_serie',
            'technical_specs' => 'especificaciones_tecnicas',
        ],
        'equipment_history' => [
            'equipment_id' => 'equipo_id',
            'user_id' => 'usuario_id',
            'event' => 'evento',
            'description' => 'descripcion',
        ],
    ];

    public function up(): void
    {
        $this->renameColumns($this->columns);
    }

    public function down(): void
    {
        $reversed = [];

        foreach ($this->columns as $table => $columns) {
            $reversed[$table] = array_flip($columns);
        }

        $this->renameColumns($reversed);
    }

    private function renameColumns(array $tables): void
    {
        foreach ($tables as $table => $columns) {
            foreach ($columns as $from => $to) {
                if (! Schema::hasColumn($table, $from) || Schema::hasColumn($table, $to)) {
                    throw new RuntimeException(
                        "No se puede renombrar {$table}.{$from} a {$to}: revisa el estado del esquema."
                    );
                }

                Schema::table($table, function (Blueprint $blueprint) use ($from, $to): void {
                    $blueprint->renameColumn($from, $to);
                });
            }
        }
    }
};
