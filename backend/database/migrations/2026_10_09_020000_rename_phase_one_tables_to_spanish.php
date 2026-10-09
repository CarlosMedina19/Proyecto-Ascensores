<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users' => 'usuarios',
        'permissions' => 'permisos',
        'role_permissions' => 'rol_permisos',
        'clients' => 'clientes',
        'client_contacts' => 'contactos_cliente',
        'buildings' => 'edificios',
        'equipment' => 'equipos',
        'elevators' => 'ascensores',
        'electric_doors' => 'puertas_electricas',
        'equipment_history' => 'historial_equipos',
        'audit_logs' => 'registros_auditoria',
    ];

    private array $sequences = [
        'users_id_seq' => 'usuarios_id_seq',
        'permissions_id_seq' => 'permisos_id_seq',
        'role_permissions_id_seq' => 'rol_permisos_id_seq',
        'clients_id_seq' => 'clientes_id_seq',
        'client_contacts_id_seq' => 'contactos_cliente_id_seq',
        'buildings_id_seq' => 'edificios_id_seq',
        'equipment_id_seq' => 'equipos_id_seq',
        'elevators_id_seq' => 'ascensores_id_seq',
        'electric_doors_id_seq' => 'puertas_electricas_id_seq',
        'equipment_history_id_seq' => 'historial_equipos_id_seq',
        'audit_logs_id_seq' => 'registros_auditoria_id_seq',
    ];

    private array $constraints = [
        'usuarios' => [
            'users_pkey' => 'usuarios_pkey',
            'users_email_unique' => 'usuarios_correo_unique',
            'users_role_id_foreign' => 'usuarios_rol_id_foreign',
        ],
        'permisos' => [
            'permissions_pkey' => 'permisos_pkey',
            'permissions_name_unique' => 'permisos_nombre_unique',
        ],
        'rol_permisos' => [
            'role_permissions_pkey' => 'rol_permisos_pkey',
            'role_permissions_permission_id_foreign' => 'rol_permisos_permiso_id_foreign',
            'role_permissions_role_id_foreign' => 'rol_permisos_rol_id_foreign',
            'role_permissions_role_id_permission_id_unique' => 'rol_permisos_rol_id_permiso_id_unique',
        ],
        'clientes' => [
            'clients_pkey' => 'clientes_pkey',
            'clients_nit_unique' => 'clientes_nit_unique',
            'clients_type_check' => 'clientes_tipo_check',
            'clients_uuid_unique' => 'clientes_identificador_uuid_unique',
        ],
        'contactos_cliente' => [
            'client_contacts_pkey' => 'contactos_cliente_pkey',
            'client_contacts_client_id_foreign' => 'contactos_cliente_cliente_id_foreign',
        ],
        'edificios' => [
            'buildings_pkey' => 'edificios_pkey',
            'buildings_client_id_foreign' => 'edificios_cliente_id_foreign',
            'buildings_uuid_unique' => 'edificios_identificador_uuid_unique',
        ],
        'equipos' => [
            'equipment_pkey' => 'equipos_pkey',
            'equipment_building_id_foreign' => 'equipos_edificio_id_foreign',
            'equipment_code_unique' => 'equipos_codigo_unique',
            'equipment_status_check' => 'equipos_estado_check',
            'equipment_type_check' => 'equipos_tipo_check',
            'equipment_uuid_unique' => 'equipos_identificador_uuid_unique',
        ],
        'ascensores' => [
            'elevators_pkey' => 'ascensores_pkey',
            'elevators_equipment_id_foreign' => 'ascensores_equipo_id_foreign',
            'elevators_equipment_id_unique' => 'ascensores_equipo_id_unique',
        ],
        'puertas_electricas' => [
            'electric_doors_pkey' => 'puertas_electricas_pkey',
            'electric_doors_equipment_id_foreign' => 'puertas_electricas_equipo_id_foreign',
            'electric_doors_equipment_id_unique' => 'puertas_electricas_equipo_id_unique',
            'electric_doors_access_type_check' => 'puertas_electricas_tipo_acceso_check',
            'electric_doors_opening_type_check' => 'puertas_electricas_tipo_apertura_check',
        ],
        'historial_equipos' => [
            'equipment_history_pkey' => 'historial_equipos_pkey',
            'equipment_history_equipment_id_foreign' => 'historial_equipos_equipo_id_foreign',
            'equipment_history_user_id_foreign' => 'historial_equipos_usuario_id_foreign',
        ],
        'registros_auditoria' => [
            'audit_logs_pkey' => 'registros_auditoria_pkey',
            'audit_logs_user_id_foreign' => 'registros_auditoria_usuario_id_foreign',
        ],
        'roles' => [
            'roles_name_unique' => 'roles_nombre_unique',
        ],
    ];

    public function up(): void
    {
        foreach ($this->tables as $from => $to) {
            $this->renameTable($from, $to);
        }

        foreach ($this->sequences as $from => $to) {
            $this->renameSequence($from, $to);
        }

        $this->renameConstraints($this->constraints);
    }

    public function down(): void
    {
        $this->renameConstraints($this->reverseMap($this->constraints));

        foreach (array_reverse($this->sequences, true) as $from => $to) {
            $this->renameSequence($to, $from);
        }

        foreach (array_reverse($this->tables, true) as $from => $to) {
            $this->renameTable($to, $from);
        }
    }

    private function renameTable(string $from, string $to): void
    {
        $hasFrom = Schema::hasTable($from);
        $hasTo = Schema::hasTable($to);

        if (! $hasFrom || $hasTo) {
            throw new RuntimeException(
                "No se puede renombrar la tabla {$from} a {$to}: revisa el estado del esquema."
            );
        }

        Schema::rename($from, $to);
    }

    private function renameSequence(string $from, string $to): void
    {
        $hasFrom = DB::scalar('SELECT to_regclass(?) IS NOT NULL', ['public.'.$from]);
        $hasTo = DB::scalar('SELECT to_regclass(?) IS NOT NULL', ['public.'.$to]);

        if ($hasFrom && $hasTo) {
            throw new RuntimeException(
                "No se puede renombrar la secuencia {$from} a {$to}: ambas existen."
            );
        }

        if (! $hasFrom || $hasTo) {
            throw new RuntimeException(
                "No se puede renombrar la secuencia {$from} a {$to}: revisa el estado del esquema."
            );
        }

        DB::statement('ALTER SEQUENCE public.'.$this->quoteIdentifier($from).' RENAME TO '.$this->quoteIdentifier($to));
    }

    private function renameConstraints(array $tables): void
    {
        foreach ($tables as $table => $constraints) {
            foreach ($constraints as $from => $to) {
                $hasFrom = $this->constraintExists($table, $from);
                $hasTo = $this->constraintExists($table, $to);

                if ($hasFrom && $hasTo) {
                    throw new RuntimeException(
                        "No se puede renombrar la restricción {$table}.{$from} a {$to}: ambas existen."
                    );
                }

                if (! $hasFrom || $hasTo) {
                    throw new RuntimeException(
                        "No se puede renombrar la restricción {$table}.{$from} a {$to}: revisa el estado del esquema."
                    );
                }

                DB::statement(
                    'ALTER TABLE '.$this->quoteIdentifier($table)
                    .' RENAME CONSTRAINT '.$this->quoteIdentifier($from)
                    .' TO '.$this->quoteIdentifier($to)
                );
            }
        }
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        return (int) DB::scalar(
            'SELECT COUNT(*) FROM pg_constraint WHERE conrelid = to_regclass(?) AND conname = ?',
            ['public.'.$table, $constraint]
        ) > 0;
    }

    private function reverseMap(array $map): array
    {
        $reversed = [];

        foreach ($map as $key => $value) {
            if (is_array($value)) {
                $reversed[$key] = array_flip($value);
            } else {
                $reversed[$value] = $key;
            }
        }

        return $reversed;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
