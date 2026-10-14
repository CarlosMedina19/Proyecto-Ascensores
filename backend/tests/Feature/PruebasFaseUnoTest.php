<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\AdministradorSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PruebasFaseUnoTest extends TestCase
{
    use RefreshDatabase;

    protected Usuario $adminUser;

    protected Rol $rolAdministrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolSeeder::class);
        $this->seed(PermisoSeeder::class);
        $this->rolAdministrador = Rol::firstOrCreate(
            ['nombre' => 'administrador'],
            ['descripcion' => 'Administrador']
        );

        $this->adminUser = Usuario::factory()->create([
            'rol_id' => $this->rolAdministrador->id,
            'activo' => true,
        ]);
    }

    public function test_user_can_login_and_receive_token(): void
    {
        $usuario = Usuario::factory()->create([
            'correo' => 'tech@ascensores.com',
            'password' => bcrypt('password123'),
            'rol_id' => $this->rolAdministrador->id,
            'activo' => true,
        ]);

        $response = $this->postJson('/api/v1/iniciar-sesion', [
            'correo' => 'tech@ascensores.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token_acceso', 'usuario']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $usuario = Usuario::factory()->create([
            'correo' => 'inactive@ascensores.com',
            'password' => bcrypt('password123'),
            'rol_id' => $this->rolAdministrador->id,
            'activo' => false,
        ]);

        $this->postJson('/api/v1/iniciar-sesion', [
            'correo' => 'inactive@ascensores.com',
            'password' => 'password123',
        ])->assertForbidden();
    }

    public function test_authenticated_user_can_access_me_and_dashboard(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $this->getJson('/api/v1/perfil')
            ->assertStatus(200)
            ->assertJsonFragment(['correo' => $this->adminUser->correo]);

        $this->getJson('/api/v1/resumen')
            ->assertStatus(200)
            ->assertJsonStructure(['exito', 'datos' => ['usuarios', 'clientes', 'edificios', 'equipos']]);
    }

    public function test_can_list_roles(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'administrador']);
    }

    public function test_can_crud_users(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $storeResponse = $this->postJson('/api/v1/usuarios', [
            'nombre' => 'Nuevo Técnico',
            'correo' => 'tecnico1@ascensores.com',
            'password' => 'secret1234',
            'rol_id' => $this->rolAdministrador->id,
            'activo' => true,
        ]);

        $storeResponse->assertStatus(201)
            ->assertJsonFragment(['correo' => 'tecnico1@ascensores.com']);

        $userId = $storeResponse->json('datos.id');

        $this->patchJson("/api/v1/usuarios/{$userId}", [
            'nombre' => 'Técnico actualizado',
            'correo' => 'tecnico.actualizado@ascensores.com',
        ])
            ->assertOk()
            ->assertJsonPath('datos.nombre', 'Técnico actualizado')
            ->assertJsonPath('datos.correo', 'tecnico.actualizado@ascensores.com');

        $this->patchJson("/api/v1/usuarios/{$userId}/alternar-estado")
            ->assertStatus(200)
            ->assertJsonPath('datos.activo', false);

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

        $clientResponse = $this->postJson('/api/v1/clientes', [
            'tipo' => 'juridica',
            'nombre' => 'Edificio Mirador Plaza',
            'nit' => '900123456-1',
            'direccion' => 'Cra 15 # 100-20',
            'telefono' => '3001234567',
            'correo' => 'admin@miradorplaza.com',
        ]);

        $clientResponse->assertStatus(201)
            ->assertJsonFragment(['nombre' => 'Edificio Mirador Plaza'])
            ->assertJsonStructure(['datos' => ['identificador_uuid']]);

        $clientId = $clientResponse->json('datos.id');

        $this->postJson("/api/v1/clientes/{$clientId}/contactos", [
            'nombre' => 'Pedro Pérez',
            'cargo' => 'Administrador',
            'telefono' => '3109876543',
            'correo' => 'pedro@miradorplaza.com',
        ])->assertStatus(201)
            ->assertJsonFragment(['nombre' => 'Pedro Pérez']);

        $this->getJson("/api/v1/clientes/{$clientId}")
            ->assertStatus(200)
            ->assertJsonFragment(['nombre' => 'Edificio Mirador Plaza']);

        $this->patchJson("/api/v1/clientes/{$clientId}", [
            'direccion' => 'Nueva dirección',
        ])
            ->assertOk()
            ->assertJsonPath('datos.direccion', 'Nueva dirección');

        $this->assertDatabaseHas('clientes', [
            'id' => $clientId,
            'direccion' => 'Nueva dirección',
        ]);
    }

    public function test_can_crud_buildings(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $cliente = Cliente::create([
            'nombre' => 'Constructora Bolívar',
            'nit' => '901234567-2',
            'estado' => true,
        ]);

        $response = $this->postJson('/api/v1/edificios', [
            'cliente_id' => $cliente->id,
            'nombre' => 'Torre Central',
            'direccion' => 'Calle 26 # 68-50',
            'ciudad' => 'Bogotá',
            'pisos' => 20,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['nombre' => 'Torre Central']);

        $buildingId = $response->json('datos.id');
        $this->patchJson("/api/v1/edificios/{$buildingId}", [
            'ciudad' => 'Medellín',
        ])
            ->assertOk()
            ->assertJsonPath('datos.ciudad', 'Medellín');

        $this->assertDatabaseHas('edificios', [
            'id' => $buildingId,
            'ciudad' => 'Medellín',
        ]);
    }

    public function test_can_create_elevator_equipment_with_specs_and_history(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $cliente = Cliente::create(['nombre' => 'Inversiones Bogotá', 'nit' => '900999888-1']);
        $edificio = Edificio::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Torre Norte',
            'direccion' => 'Cra 7 # 72-10',
        ]);

        $response = $this->postJson('/api/v1/equipos', [
            'edificio_id' => $edificio->id,
            'codigo' => 'ASC-001',
            'tipo' => 'ascensor',
            'marca' => 'Otis',
            'modelo' => 'Gen2',
            'numero_serie' => 'OT-98765',
            'ubicacion' => 'Torre Norte - Fila A',
            'estado' => 'activo',
            'ascensor' => [
                'capacidad_kg' => 1000,
                'paradas' => 15,
                'velocidad_mpm' => 120.0,
                'tipo_traccion' => 'Tracción con cintas planas',
                'motor' => 'Imanes permanentes ReGen',
                'controlador' => 'GCS2200',
                'tipo_puerta' => 'Apertura central 2 hojas',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('datos.codigo', 'ASC-001')
            ->assertJsonPath('datos.ascensor.paradas', 15)
            ->assertJsonPath('datos.ascensor.capacidad_kg', 1000);

        $equipmentId = $response->json('datos.id');

        $this->getJson("/api/v1/equipos/{$equipmentId}/historial")
            ->assertStatus(200)
            ->assertJsonFragment(['evento' => 'alta_equipo']);

        $this->postJson("/api/v1/equipos/{$equipmentId}/historial", [
            'evento' => 'inspeccion_inicial',
            'descripcion' => 'Inspección de entrega satisfactoria.',
        ])->assertStatus(201)
            ->assertJsonFragment(['evento' => 'inspeccion_inicial']);

        $this->patchJson("/api/v1/equipos/{$equipmentId}", [
            'estado' => 'mantenimiento',
            'ascensor' => ['capacidad_kg' => 1200],
        ])
            ->assertOk()
            ->assertJsonPath('datos.estado', 'mantenimiento')
            ->assertJsonPath('datos.ascensor.capacidad_kg', 1200);

        $this->assertDatabaseHas('equipos', [
            'id' => $equipmentId,
            'estado' => 'mantenimiento',
        ]);
        $this->assertDatabaseHas('ascensores', [
            'equipo_id' => $equipmentId,
            'capacidad_kg' => 1200,
        ]);
    }

    public function test_user_without_permission_cannot_access_protected_catalogs(): void
    {
        $technicianRole = Rol::where('nombre', 'tecnico')->firstOrFail();
        $technician = Usuario::factory()->create([
            'rol_id' => $technicianRole->id,
            'activo' => true,
        ]);

        $this->actingAs($technician, 'sanctum')
            ->getJson('/api/v1/usuarios')
            ->assertForbidden();
    }

    public function test_seeded_admin_can_access_roles_and_audit_logs(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $this->getJson('/api/v1/roles')->assertOk();
        $this->getJson('/api/v1/permisos')
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'usuarios.ver']);
        $this->getJson('/api/v1/registros-auditoria')->assertOk();
    }

    public function test_admin_can_create_roles_and_assign_seeded_permissions(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $roleResponse = $this->postJson('/api/v1/roles', [
            'nombre' => 'auditor',
            'descripcion' => 'Consulta de auditoría',
        ]);

        $roleResponse->assertCreated()
            ->assertJsonPath('datos.nombre', 'auditor');

        $roleId = $roleResponse->json('datos.id');

        $this->patchJson("/api/v1/roles/{$roleId}", [
            'descripcion' => 'Auditor del sistema',
        ])
            ->assertOk()
            ->assertJsonPath('datos.descripcion', 'Auditor del sistema');

        $permsResponse = $this->putJson("/api/v1/roles/{$roleId}/permisos", [
            'permisos' => ['registros_auditoria.ver', 'clientes.ver'],
        ])
            ->assertOk();

        $permisos = $permsResponse->json('datos.permisos');
        $this->assertContains('clientes.ver', $permisos);
        $this->assertContains('registros_auditoria.ver', $permisos);

        $this->assertDatabaseHas('registros_auditoria', [
            'accion' => 'permisos_actualizados',
            'modelo' => Rol::class,
            'modelo_id' => $roleId,
        ]);

        $this->assertDatabaseHas('rol_permisos', [
            'rol_id' => $roleId,
            'permiso_id' => Permiso::where('nombre', 'registros_auditoria.ver')->value('id'),
        ]);

        $this->putJson("/api/v1/roles/{$roleId}/permisos", [
            'permisos' => [],
        ])
            ->assertOk();
    }

    public function test_admin_role_cannot_be_renamed_or_have_its_permissions_changed(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $this->patchJson('/api/v1/roles/'.$this->rolAdministrador->id, [
            'nombre' => 'locked-admin',
        ])->assertForbidden();

        $this->putJson('/api/v1/roles/'.$this->rolAdministrador->id.'/permisos', [
            'permisos' => [],
        ])->assertForbidden();
    }

    public function test_role_management_and_permission_catalog_require_permissions(): void
    {
        $technicianRole = Rol::where('nombre', 'tecnico')->firstOrFail();
        $technician = Usuario::factory()->create([
            'rol_id' => $technicianRole->id,
            'activo' => true,
        ]);

        $this->actingAs($technician, 'sanctum')
            ->getJson('/api/v1/permisos')
            ->assertForbidden();

        $this->postJson('/api/v1/roles', [
            'nombre' => 'unauthorized-role',
        ])->assertForbidden();
    }

    public function test_users_api_does_not_offer_hard_delete(): void
    {
        $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson('/api/v1/usuarios/'.$this->adminUser->id)
            ->assertMethodNotAllowed();
    }

    public function test_admin_seeder_requires_an_explicit_password_for_new_accounts(): void
    {
        config([
            'admin.correo' => 'admin@ascensores.test',
            'admin.password' => '',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->seed(AdministradorSeeder::class);
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/iniciar-sesion', [
                'correo' => 'throttle@ascensores.test',
                'password' => 'incorrecta',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/iniciar-sesion', [
            'correo' => 'throttle@ascensores.test',
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
    }

    public function test_user_verification_timestamp_column_migration_is_reversible(): void
    {
        $usuario = Usuario::factory()->create();
        $verifiedAt = now()->startOfSecond();

        DB::table('usuarios')
            ->where('id', $usuario->id)
            ->update(['correo_verificado_en' => $verifiedAt]);

        $migration = require database_path(
            'migrations/2026_10_09_030000_translate_user_verification_column_to_spanish.php'
        );

        $migration->down();
        $this->assertTrue(Schema::hasColumn('usuarios', 'email_verified_at'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('usuarios', 'correo_verificado_en'));
        $this->assertFalse(Schema::hasColumn('usuarios', 'email_verified_at'));
    }

    public function test_table_rename_migration_preserves_relationships_and_sanctum_tokens(): void
    {
        $permiso = Permiso::where('nombre', 'clientes.ver')->firstOrFail();
        $tokenAcceso = $this->adminUser->createToken('table-rename-test');
        $cliente = Cliente::create([
            'nombre' => 'Cliente antes del renombrado',
            'nit' => '900555444-1',
        ]);
        $contacto = $cliente->contactos()->create(['nombre' => 'Contacto previo']);
        $edificio = $cliente->edificios()->create([
            'nombre' => 'Edificio antes del renombrado',
            'direccion' => 'Calle 10',
        ]);
        $equipo = $edificio->equipos()->create([
            'codigo' => 'ASC-RENOMBRE',
            'tipo' => 'ascensor',
            'estado' => 'activo',
        ]);
        $equipo->ascensor()->create(['capacidad_kg' => 800]);
        $equipo->historial()->create([
            'usuario_id' => $this->adminUser->id,
            'evento' => 'prueba_renombrado',
        ]);

        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'nombre' => 'Cliente antes del renombrado']);
        $this->assertDatabaseHas('contactos_cliente', ['id' => $contacto->id]);
        $this->assertDatabaseHas('permisos', ['id' => $permiso->id]);
        $this->assertDatabaseHas('usuarios', ['id' => $this->adminUser->id]);
    }

    public function test_spanish_column_migration_preserves_existing_master_data(): void
    {
        $usuario = Usuario::factory()->create(['activo' => true]);
        $cliente = Cliente::create(['nombre' => 'Test', 'nit' => '901111111-1', 'estado' => true]);
        $edificio = $cliente->edificios()->create(['nombre' => 'Edificio Test', 'direccion' => 'Calle Test']);
        $equipo = $edificio->equipos()->create(['codigo' => 'ASC-TEST', 'tipo' => 'ascensor', 'estado' => 'activo']);

        $this->assertDatabaseHas('usuarios', ['id' => $usuario->id, 'activo' => true]);
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'estado' => true]);
        $this->assertDatabaseHas('equipos', ['id' => $equipo->id, 'estado' => 'activo']);
    }
}
