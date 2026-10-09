<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Building;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseOneApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->adminRole = Role::firstOrCreate(
            ['nombre' => 'admin'],
            ['descripcion' => 'Administrador']
        );

        $this->adminUser = User::factory()->create([
            'rol_id' => $this->adminRole->id,
            'activo' => true,
        ]);
    }

    public function test_user_can_login_and_receive_token(): void
    {
        $user = User::factory()->create([
            'correo' => 'tech@ascensores.com',
            'password' => bcrypt('password123'),
            'rol_id' => $this->adminRole->id,
            'activo' => true,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'tech@ascensores.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'correo' => 'inactive@ascensores.com',
            'password' => bcrypt('password123'),
            'rol_id' => $this->adminRole->id,
            'activo' => false,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'inactive@ascensores.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Usuario inactivo']);
    }

    public function test_authenticated_user_can_access_me_and_dashboard(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $this->getJson('/api/v1/me')
            ->assertStatus(200)
            ->assertJsonFragment(['email' => $this->adminUser->correo]);

        $this->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['users', 'clients', 'buildings', 'equipment']]);
    }

    public function test_can_list_roles(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $this->getJson('/api/v1/roles')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'admin']);
    }

    public function test_can_crud_users(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $storeResponse = $this->postJson('/api/v1/users', [
            'name' => 'Nuevo Técnico',
            'email' => 'tecnico1@ascensores.com',
            'password' => 'secret1234',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $storeResponse->assertStatus(201)
            ->assertJsonFragment(['email' => 'tecnico1@ascensores.com']);

        $userId = $storeResponse->json('data.id');

        $this->patchJson("/api/v1/users/{$userId}", [
            'name' => 'Técnico actualizado',
            'email' => 'tecnico.actualizado@ascensores.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Técnico actualizado')
            ->assertJsonPath('data.email', 'tecnico.actualizado@ascensores.com');

        $this->patchJson("/api/v1/users/{$userId}/toggle-status")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('usuarios', [
            'id' => $userId,
            'nombre' => 'Técnico actualizado',
            'correo' => 'tecnico.actualizado@ascensores.com',
            'activo' => false,
        ]);
    }

    public function test_can_crud_clients_and_add_contacts(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $clientResponse = $this->postJson('/api/v1/clients', [
            'type' => 'juridica',
            'name' => 'Edificio Mirador Plaza',
            'nit' => '900123456-1',
            'address' => 'Cra 15 # 100-20',
            'phone' => '3001234567',
            'email' => 'admin@miradorplaza.com',
        ]);

        $clientResponse->assertStatus(201)
            ->assertJsonFragment(['name' => 'Edificio Mirador Plaza'])
            ->assertJsonStructure(['data' => ['uuid']]);

        $clientId = $clientResponse->json('data.id');

        $this->postJson("/api/v1/clients/{$clientId}/contacts", [
            'name' => 'Pedro Pérez',
            'position' => 'Administrador',
            'phone' => '3109876543',
            'email' => 'pedro@miradorplaza.com',
        ])->assertStatus(201)
            ->assertJsonFragment(['name' => 'Pedro Pérez']);

        $this->getJson("/api/v1/clients/{$clientId}")
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Edificio Mirador Plaza']);

        $this->patchJson("/api/v1/clients/{$clientId}", [
            'address' => 'Nueva dirección',
        ])
            ->assertOk()
            ->assertJsonPath('data.address', 'Nueva dirección');

        $this->assertDatabaseHas('clientes', [
            'id' => $clientId,
            'direccion' => 'Nueva dirección',
        ]);
    }

    public function test_can_crud_buildings(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $client = Client::create([
            'nombre' => 'Constructora Bolívar',
            'nit' => '901234567-2',
            'estado' => true,
        ]);

        $response = $this->postJson('/api/v1/buildings', [
            'client_id' => $client->id,
            'name' => 'Torre Central',
            'address' => 'Calle 26 # 68-50',
            'city' => 'Bogotá',
            'floors' => 20,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Torre Central']);

        $buildingId = $response->json('data.id');
        $this->patchJson("/api/v1/buildings/{$buildingId}", [
            'city' => 'Medellín',
        ])
            ->assertOk()
            ->assertJsonPath('data.city', 'Medellín');

        $this->assertDatabaseHas('edificios', [
            'id' => $buildingId,
            'ciudad' => 'Medellín',
        ]);
    }

    public function test_can_create_elevator_equipment_with_specs_and_history(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $client = Client::create(['nombre' => 'Inversiones Bogotá', 'nit' => '900999888-1']);
        $building = Building::create([
            'cliente_id' => $client->id,
            'nombre' => 'Torre Norte',
            'direccion' => 'Cra 7 # 72-10',
        ]);

        $response = $this->postJson('/api/v1/equipment', [
            'building_id' => $building->id,
            'code' => 'ASC-001',
            'type' => 'elevator',
            'brand' => 'Otis',
            'model' => 'Gen2',
            'serial_number' => 'OT-98765',
            'location' => 'Torre Norte - Fila A',
            'status' => 'active',
            'elevator' => [
                'capacity_kg' => 1000,
                'stops' => 15,
                'speed_mpm' => 120.0,
                'drive_type' => 'Tracción con cintas planas',
                'motor' => 'Imanes permanentes ReGen',
                'controller' => 'GCS2200',
                'door_type' => 'Apertura central 2 hojas',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'ASC-001')
            ->assertJsonPath('data.elevator.stops', 15)
            ->assertJsonPath('data.elevator.capacity_kg', 1000);

        $equipmentId = $response->json('data.id');

        $this->getJson("/api/v1/equipment/{$equipmentId}/history")
            ->assertStatus(200)
            ->assertJsonFragment(['event' => 'alta_equipo']);

        $this->postJson("/api/v1/equipment/{$equipmentId}/history", [
            'event' => 'inspeccion_inicial',
            'description' => 'Inspección de entrega satisfactoria.',
        ])->assertStatus(201)
            ->assertJsonFragment(['event' => 'inspeccion_inicial']);

        $this->patchJson("/api/v1/equipment/{$equipmentId}", [
            'status' => 'maintenance',
            'elevator' => ['capacity_kg' => 1200],
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'maintenance')
            ->assertJsonPath('data.elevator.capacity_kg', 1200);

        $this->assertDatabaseHas('equipos', [
            'id' => $equipmentId,
            'estado' => 'maintenance',
        ]);
        $this->assertDatabaseHas('ascensores', [
            'equipo_id' => $equipmentId,
            'capacidad_kg' => 1200,
        ]);
    }

    public function test_user_without_permission_cannot_access_protected_catalogs(): void
    {
        $technicianRole = Role::where('nombre', 'tecnico')->firstOrFail();
        $technician = User::factory()->create([
            'rol_id' => $technicianRole->id,
            'activo' => true,
        ]);

        $this->actingAs($technician, 'sanctum')
            ->getJson('/api/v1/users')
            ->assertForbidden();
    }

    public function test_seeded_admin_can_access_roles_and_audit_logs(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $this->getJson('/api/v1/roles')->assertOk();
        $this->getJson('/api/v1/audit-logs')->assertOk();
    }

    public function test_users_api_does_not_offer_hard_delete(): void
    {
        $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson('/api/v1/users/'.$this->adminUser->id)
            ->assertMethodNotAllowed();
    }

    public function test_admin_seeder_requires_an_explicit_password_for_new_accounts(): void
    {
        config([
            'admin.email' => 'admin@ascensores.test',
            'admin.password' => '',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->seed(AdminUserSeeder::class);
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/login', [
                'email' => 'throttle@ascensores.test',
                'password' => 'incorrecta',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/login', [
            'email' => 'throttle@ascensores.test',
            'password' => 'incorrecta',
        ])->assertTooManyRequests();
    }

    public function test_domain_and_user_columns_are_stored_in_spanish_and_framework_fields_are_preserved(): void
    {
        $this->assertTrue(Schema::hasTable('usuarios'));
        $this->assertTrue(Schema::hasTable('permisos'));
        $this->assertTrue(Schema::hasTable('rol_permisos'));
        $this->assertTrue(Schema::hasTable('clientes'));
        $this->assertTrue(Schema::hasTable('contactos_cliente'));
        $this->assertTrue(Schema::hasTable('edificios'));
        $this->assertTrue(Schema::hasTable('equipos'));
        $this->assertTrue(Schema::hasTable('ascensores'));
        $this->assertTrue(Schema::hasTable('puertas_electricas'));
        $this->assertTrue(Schema::hasTable('historial_equipos'));
        $this->assertTrue(Schema::hasTable('registros_auditoria'));
        $this->assertTrue(Schema::hasColumn('usuarios', 'nombre'));
        $this->assertTrue(Schema::hasColumn('usuarios', 'correo'));
        $this->assertTrue(Schema::hasColumn('usuarios', 'correo_verificado_en'));
        $this->assertTrue(Schema::hasColumn('clientes', 'identificador_uuid'));
        $this->assertTrue(Schema::hasColumn('edificios', 'identificador_uuid'));
        $this->assertTrue(Schema::hasColumn('equipos', 'identificador_uuid'));
        $this->assertTrue(Schema::hasColumn('usuarios', 'password'));
        $this->assertTrue(Schema::hasColumn('usuarios', 'created_at'));
        $this->assertTrue(Schema::hasColumn('usuarios', 'rol_id'));
        $this->assertTrue(Schema::hasColumn('rol_permisos', 'permiso_id'));
        $this->assertTrue(Schema::hasColumn('rol_permisos', 'rol_id'));
        $this->assertTrue(Schema::hasColumn('clientes', 'direccion'));
        $this->assertTrue(Schema::hasColumn('contactos_cliente', 'cliente_id'));
        $this->assertTrue(Schema::hasColumn('edificios', 'cliente_id'));
        $this->assertTrue(Schema::hasColumn('equipos', 'edificio_id'));
        $this->assertTrue(Schema::hasColumn('ascensores', 'equipo_id'));
        $this->assertTrue(Schema::hasColumn('ascensores', 'capacidad_kg'));
        $this->assertTrue(Schema::hasColumn('puertas_electricas', 'equipo_id'));
        $this->assertTrue(Schema::hasColumn('puertas_electricas', 'tipo_apertura'));
        $this->assertTrue(Schema::hasColumn('historial_equipos', 'usuario_id'));
        $this->assertTrue(Schema::hasColumn('registros_auditoria', 'usuario_id'));
        $this->assertTrue(Schema::hasColumn('personal_access_tokens', 'token'));
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('permissions'));
        $this->assertFalse(Schema::hasTable('role_permissions'));
        $this->assertFalse(Schema::hasTable('clients'));
        $this->assertFalse(Schema::hasTable('client_contacts'));
        $this->assertFalse(Schema::hasTable('buildings'));
        $this->assertFalse(Schema::hasTable('equipment'));
        $this->assertFalse(Schema::hasTable('elevators'));
        $this->assertFalse(Schema::hasTable('electric_doors'));
        $this->assertFalse(Schema::hasTable('equipment_history'));
        $this->assertFalse(Schema::hasTable('audit_logs'));
        $this->assertFalse(Schema::hasColumn('usuarios', 'name'));
        $this->assertFalse(Schema::hasColumn('usuarios', 'email_verified_at'));
        $this->assertFalse(Schema::hasColumn('clientes', 'uuid'));
        $this->assertFalse(Schema::hasColumn('edificios', 'uuid'));
        $this->assertFalse(Schema::hasColumn('equipos', 'uuid'));
        $this->assertFalse(Schema::hasColumn('puertas_electricas', 'equipment_id'));
        $this->assertFalse(Schema::hasColumn('clientes', 'address'));
        $this->assertFalse(Schema::hasColumn('equipos', 'building_id'));
        $this->assertFalse(Schema::hasColumn('usuarios', 'role_id'));
        $this->assertFalse(Schema::hasColumn('rol_permisos', 'permission_id'));
        $this->assertFalse(Schema::hasColumn('rol_permisos', 'role_id'));
        $this->assertFalse(Schema::hasColumn('contactos_cliente', 'client_id'));
        $this->assertFalse(Schema::hasColumn('ascensores', 'equipment_id'));
        $this->assertFalse(Schema::hasColumn('puertas_electricas', 'equipment_id'));
        $this->assertFalse(Schema::hasColumn('historial_equipos', 'user_id'));
        $this->assertFalse(Schema::hasColumn('registros_auditoria', 'user_id'));
    }

    public function test_user_verification_timestamp_column_migration_is_reversible(): void
    {
        $user = User::factory()->create();
        $verifiedAt = now()->startOfSecond();

        DB::table('usuarios')
            ->where('id', $user->id)
            ->update(['correo_verificado_en' => $verifiedAt]);

        $migration = require database_path(
            'migrations/2026_10_09_030000_translate_user_verification_column_to_spanish.php'
        );

        $migration->down();
        $this->assertTrue(Schema::hasColumn('usuarios', 'email_verified_at'));
        $this->assertSame(
            $verifiedAt->format('Y-m-d H:i:s'),
            DB::table('usuarios')->where('id', $user->id)->value('email_verified_at')
        );

        $migration->up();
        $this->assertTrue(Schema::hasColumn('usuarios', 'correo_verificado_en'));
        $this->assertFalse(Schema::hasColumn('usuarios', 'email_verified_at'));
        $this->assertSame(
            $verifiedAt->format('Y-m-d H:i:s'),
            DB::table('usuarios')->where('id', $user->id)->value('correo_verificado_en')
        );
        $this->assertEquals($verifiedAt, User::findOrFail($user->id)->correo_verificado_en);
    }

    public function test_table_rename_migration_preserves_relationships_and_sanctum_tokens(): void
    {
        $permission = Permission::where('nombre', 'clients.view')->firstOrFail();
        $token = $this->adminUser->createToken('table-rename-test');
        $client = Client::create([
            'nombre' => 'Cliente antes del renombrado',
            'nit' => '900555444-1',
        ]);
        $contact = $client->contacts()->create(['nombre' => 'Contacto previo']);
        $building = $client->buildings()->create([
            'nombre' => 'Edificio antes del renombrado',
            'direccion' => 'Calle 10',
        ]);
        $equipment = $building->equipment()->create([
            'codigo' => 'ASC-RENOMBRE',
            'tipo' => 'elevator',
        ]);
        $equipment->elevator()->create(['capacidad_kg' => 800]);
        $equipment->history()->create([
            'usuario_id' => $this->adminUser->id,
            'evento' => 'prueba_renombrado',
        ]);
        AuditLog::create([
            'usuario_id' => $this->adminUser->id,
            'accion' => 'prueba_renombrado',
            'modelo' => 'Equipment',
            'modelo_id' => $equipment->id,
            'cambios' => ['status' => 'active'],
        ]);

        $migration = require database_path(
            'migrations/2026_10_09_020000_rename_phase_one_tables_to_spanish.php'
        );

        $migration->down();
        $this->assertSame('Cliente antes del renombrado', DB::table('clients')->where('id', $client->id)->value('nombre'));
        $this->assertSame($contact->id, DB::table('client_contacts')->where('id', $contact->id)->value('id'));
        $this->assertSame($permission->id, DB::table('permissions')->where('id', $permission->id)->value('id'));
        $this->assertSame($this->adminUser->id, DB::table('users')->where('id', $this->adminUser->id)->value('id'));

        $migration->up();
        $this->assertSame('Cliente antes del renombrado', DB::table('clientes')->where('id', $client->id)->value('nombre'));
        $this->assertSame($contact->id, DB::table('contactos_cliente')->where('id', $contact->id)->value('id'));
        $this->assertSame($permission->id, DB::table('permisos')->where('id', $permission->id)->value('id'));
        $this->assertSame($this->adminUser->id, DB::table('usuarios')->where('id', $this->adminUser->id)->value('id'));
        $loadedClient = Client::with(['contacts', 'buildings.equipment.elevator', 'buildings.equipment.history'])
            ->findOrFail($client->id);
        $this->assertSame($contact->id, $loadedClient->contacts->sole()->id);
        $this->assertSame($building->id, $loadedClient->buildings->sole()->id);
        $this->assertSame($equipment->id, $loadedClient->buildings->sole()->equipment->sole()->id);
        $this->assertTrue($this->adminRole->hasPermission('clients.view'));
        $this->assertSame(1, $this->adminUser->tokens()->whereKey($token->accessToken->id)->count());
        $this->assertNotSame($this->adminUser->id, User::factory()->create(['rol_id' => $this->adminRole->id])->id);
        $this->assertSame(
            'registros_auditoria_pkey',
            DB::table('pg_constraint')
                ->where('conrelid', DB::raw("to_regclass('public.registros_auditoria')"))
                ->where('contype', 'p')
                ->value('conname')
        );
        $this->assertSame('registros_auditoria_pkey', DB::scalar("SELECT to_regclass('public.registros_auditoria_pkey')::text"));
        $this->assertSame('usuarios_id_seq', DB::scalar("SELECT pg_get_serial_sequence('usuarios', 'id')::regclass::text"));
    }

    public function test_spanish_column_migration_preserves_existing_master_data(): void
    {
        $client = Client::create([
            'nombre' => 'Cliente existente',
            'nit' => '900111222-3',
            'direccion' => 'Calle 10',
        ]);
        $building = Building::create([
            'cliente_id' => $client->id,
            'nombre' => 'Edificio existente',
            'direccion' => 'Carrera 20',
        ]);
        $equipment = Equipment::create([
            'edificio_id' => $building->id,
            'codigo' => 'ASC-LEGADO',
            'tipo' => 'elevator',
        ]);
        $equipment->elevator()->create([
            'marca' => 'Otis',
            'capacidad_kg' => 800,
        ]);
        DB::table('puertas_electricas')->insert([
            'equipo_id' => $equipment->id,
            'marca' => 'Marca de puerta',
            'tipo_puerta' => 'Automática',
            'tipo_apertura' => 'doble',
            'tipo_acceso' => 'ambos',
            'numero_serie' => 'PUERTA-LEGADA',
            'especificaciones_tecnicas' => 'Datos que deben conservarse',
        ]);

        $tableMigration = require database_path(
            'migrations/2026_10_09_020000_rename_phase_one_tables_to_spanish.php'
        );
        $columnMigration = require database_path(
            'migrations/2026_10_08_000000_translate_phase_one_columns_to_spanish.php'
        );

        $tableMigration->down();
        $columnMigration->down();
        $this->assertSame('Cliente existente', DB::table('clients')->value('name'));
        $this->assertSame($client->identificador_uuid, DB::table('clients')->value('uuid'));
        $this->assertSame('Edificio existente', DB::table('buildings')->value('name'));
        $this->assertSame($building->identificador_uuid, DB::table('buildings')->value('uuid'));
        $this->assertSame('ASC-LEGADO', DB::table('equipment')->value('code'));
        $this->assertSame($equipment->identificador_uuid, DB::table('equipment')->value('uuid'));
        $this->assertSame('PUERTA-LEGADA', DB::table('electric_doors')->value('serial_number'));

        $columnMigration->up();
        $tableMigration->up();
        $this->assertSame('Cliente existente', DB::table('clientes')->value('nombre'));
        $this->assertSame($client->identificador_uuid, DB::table('clientes')->value('identificador_uuid'));
        $this->assertSame('Edificio existente', DB::table('edificios')->value('nombre'));
        $this->assertSame($building->identificador_uuid, DB::table('edificios')->value('identificador_uuid'));
        $this->assertSame('ASC-LEGADO', DB::table('equipos')->value('codigo'));
        $this->assertSame($equipment->identificador_uuid, DB::table('equipos')->value('identificador_uuid'));
        $this->assertSame(800, DB::table('ascensores')->value('capacidad_kg'));
        $this->assertSame('PUERTA-LEGADA', DB::table('puertas_electricas')->value('numero_serie'));
    }
}
