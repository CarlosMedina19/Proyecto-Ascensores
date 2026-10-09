<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permisos = [
        'dashboard.view' => 'resumen.ver',
        'roles.view' => 'roles.ver',
        'roles.create' => 'roles.crear',
        'roles.update' => 'roles.editar',
        'roles.permissions.update' => 'roles.permisos.editar',
        'permissions.view' => 'permisos.ver',
        'users.view' => 'usuarios.ver',
        'users.create' => 'usuarios.crear',
        'users.update' => 'usuarios.editar',
        'audit-logs.view' => 'registros_auditoria.ver',
        'clients.view' => 'clientes.ver',
        'clients.create' => 'clientes.crear',
        'clients.update' => 'clientes.editar',
        'clients.delete' => 'clientes.eliminar',
        'clients.contacts.create' => 'clientes.contactos.crear',
        'buildings.view' => 'edificios.ver',
        'buildings.create' => 'edificios.crear',
        'buildings.update' => 'edificios.editar',
        'buildings.delete' => 'edificios.eliminar',
        'equipment.view' => 'equipos.ver',
        'equipment.create' => 'equipos.crear',
        'equipment.update' => 'equipos.editar',
        'equipment.delete' => 'equipos.eliminar',
        'equipment.history.view' => 'equipos.historial.ver',
        'equipment.history.create' => 'equipos.historial.crear',
    ];

    private array $modelos = [
        'App\\Models\\User' => 'App\\Models\\Usuario',
        'App\\Models\\Client' => 'App\\Models\\Cliente',
        'App\\Models\\Building' => 'App\\Models\\Edificio',
        'App\\Models\\Equipment' => 'App\\Models\\Equipo',
        'App\\Models\\EquipmentHistory' => 'App\\Models\\HistorialEquipo',
        'App\\Models\\ClientContact' => 'App\\Models\\ContactoCliente',
        'App\\Models\\Role' => 'App\\Models\\Rol',
        'App\\Models\\Permission' => 'App\\Models\\Permiso',
        'App\\Models\\AuditLog' => 'App\\Models\\RegistroAuditoria',
        'App\\Models\\Elevator' => 'App\\Models\\Ascensor',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE equipos DROP CONSTRAINT IF EXISTS equipos_tipo_check');
            DB::statement('ALTER TABLE equipos DROP CONSTRAINT IF EXISTS equipos_estado_check');
        }

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->foreignId('cliente_id')->nullable()->after('rol_id')
                ->constrained('clientes')->nullOnDelete();
        });

        DB::table('roles')->where('nombre', 'admin')->update(['nombre' => 'administrador']);

        foreach ($this->permisos as $anterior => $nuevo) {
            DB::table('permisos')->where('nombre', $anterior)->update(['nombre' => $nuevo]);
        }

        DB::table('equipos')->where('tipo', 'elevator')->update(['tipo' => 'ascensor']);
        DB::table('equipos')->where('estado', 'active')->update(['estado' => 'activo']);
        DB::table('equipos')->where('estado', 'inactive')->update(['estado' => 'inactivo']);
        DB::table('equipos')->where('estado', 'maintenance')->update(['estado' => 'mantenimiento']);
        DB::table('registros_auditoria')->where('accion', 'created')->update(['accion' => 'creado']);
        DB::table('registros_auditoria')->where('accion', 'updated')->update(['accion' => 'actualizado']);
        DB::table('registros_auditoria')->where('accion', 'deleted')->update(['accion' => 'eliminado']);

        foreach ($this->modelos as $anterior => $nuevo) {
            DB::table('registros_auditoria')->where('modelo', $anterior)->update(['modelo' => $nuevo]);
            DB::table('personal_access_tokens')->where('tokenable_type', $anterior)->update(['tokenable_type' => $nuevo]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE equipos ADD CONSTRAINT equipos_tipo_check CHECK (tipo = 'ascensor')");
            DB::statement("ALTER TABLE equipos ADD CONSTRAINT equipos_estado_check CHECK (estado IN ('activo', 'inactivo', 'mantenimiento'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE equipos DROP CONSTRAINT IF EXISTS equipos_tipo_check');
            DB::statement('ALTER TABLE equipos DROP CONSTRAINT IF EXISTS equipos_estado_check');
        }

        foreach (array_reverse($this->modelos, true) as $anterior => $nuevo) {
            DB::table('registros_auditoria')->where('modelo', $nuevo)->update(['modelo' => $anterior]);
            DB::table('personal_access_tokens')->where('tokenable_type', $nuevo)->update(['tokenable_type' => $anterior]);
        }

        DB::table('registros_auditoria')->where('accion', 'creado')->update(['accion' => 'created']);
        DB::table('registros_auditoria')->where('accion', 'actualizado')->update(['accion' => 'updated']);
        DB::table('registros_auditoria')->where('accion', 'eliminado')->update(['accion' => 'deleted']);
        DB::table('equipos')->where('estado', 'activo')->update(['estado' => 'active']);
        DB::table('equipos')->where('estado', 'inactivo')->update(['estado' => 'inactive']);
        DB::table('equipos')->where('estado', 'mantenimiento')->update(['estado' => 'maintenance']);
        DB::table('equipos')->where('tipo', 'ascensor')->update(['tipo' => 'elevator']);

        foreach (array_reverse($this->permisos, true) as $anterior => $nuevo) {
            DB::table('permisos')->where('nombre', $nuevo)->update(['nombre' => $anterior]);
        }

        DB::table('roles')->where('nombre', 'administrador')->update(['nombre' => 'admin']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE equipos ADD CONSTRAINT equipos_tipo_check CHECK (tipo = 'elevator')");
            DB::statement("ALTER TABLE equipos ADD CONSTRAINT equipos_estado_check CHECK (estado IN ('active', 'inactive', 'maintenance'))");
        }

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cliente_id');
        });
    }
};
