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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pruebas funcionales de la fase 1: RF-001 a RF-009.
 */
class PruebasFaseUnoTest extends TestCase
{
    use RefreshDatabase;

    protected Usuario $administrador;

    protected Rol $rolAdministrador;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seed(RolSeeder::class);
        $this->seed(PermisoSeeder::class);
        $this->rolAdministrador = Rol::where('nombre', 'administrador')->firstOrFail();

        $this->administrador = Usuario::factory()->create([
            'rol_id' => $this->rolAdministrador->id,
            'activo' => true,
        ]);
    }

    private function crearCliente(array $datos = []): Cliente
    {
        return Cliente::create($datos + [
            'tipo' => 'juridica',
            'nombre' => 'Constructora Bolívar',
            'nit' => '901234567-2',
            'estado' => true,
        ]);
    }

    private function crearEdificio(Cliente $cliente, array $datos = []): Edificio
    {
        return $cliente->edificios()->create($datos + [
            'nombre' => 'Torre Norte',
            'direccion' => 'Cra 7 # 72-10',
            'ciudad' => 'Bogotá',
            'nombre_contacto' => 'Ana Torres',
            'telefono_contacto' => '3001112233',
        ]);
    }

    private function datosAscensor(array $datos = []): array
    {
        return $datos + [
            'capacidad_kg' => 1000,
            'paradas' => 15,
            'velocidad_mpm' => 120.0,
            'tipo_traccion' => 'Tracción con cintas planas',
            'motor' => 'Imanes permanentes ReGen',
            'controlador' => 'GCS2200',
            'tipo_puerta' => 'Apertura central 2 hojas',
            'puertas_cabina' => 1,
            'puertas_piso' => 15,
        ];
    }

    public function test_usuario_inicia_sesion_y_recibe_token(): void
    {
        Usuario::factory()->create([
            'correo' => 'tech@ascensores.com',
            'password' => 'password123',
            'rol_id' => $this->rolAdministrador->id,
            'activo' => true,
        ]);

        $this->postJson('/api/v1/iniciar-sesion', [
            'correo' => 'TECH@ascensores.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonStructure(['token_acceso', 'usuario' => ['rol' => ['permisos']], 'minutos_inactividad']);

        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'inicio_sesion', 'modulo' => 'autenticacion']);
    }

    public function test_usuario_inactivo_no_inicia_sesion(): void
    {
        Usuario::factory()->create([
            'correo' => 'inactive@ascensores.com',
            'password' => 'password123',
            'rol_id' => $this->rolAdministrador->id,
            'activo' => false,
        ]);

        $this->postJson('/api/v1/iniciar-sesion', [
            'correo' => 'inactive@ascensores.com',
            'password' => 'password123',
        ])->assertForbidden()->assertJsonStructure(['mensaje']);
    }

    public function test_usuario_autenticado_consulta_perfil_y_resumen(): void
    {
        $this->actingAs($this->administrador, 'sanctum');

        $this->getJson('/api/v1/perfil')
            ->assertOk()
            ->assertJsonFragment(['correo' => $this->administrador->correo]);

        $this->getJson('/api/v1/resumen')
            ->assertOk()
            ->assertJsonStructure(['exito', 'datos' => ['usuarios', 'clientes', 'edificios', 'equipos', 'ultimos_eventos']]);
    }

    public function test_lista_roles(): void
    {
        $this->actingAs($this->administrador, 'sanctum');

        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'administrador']);
    }

    public function test_crud_de_usuarios(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $rolTecnico = Rol::where('nombre', 'tecnico')->firstOrFail();

        $respuesta = $this->postJson('/api/v1/usuarios', [
            'nombre' => 'Nuevo Técnico',
            'correo' => 'tecnico1@ascensores.com',
            'password' => 'secreto1234',
            'rol_id' => $rolTecnico->id,
            'activo' => true,
        ]);

        $respuesta->assertCreated()->assertJsonFragment(['correo' => 'tecnico1@ascensores.com']);
        $usuarioId = $respuesta->json('datos.id');

        $this->patchJson("/api/v1/usuarios/{$usuarioId}", [
            'nombre' => 'Técnico actualizado',
            'correo' => 'tecnico.actualizado@ascensores.com',
        ])
            ->assertOk()
            ->assertJsonPath('datos.nombre', 'Técnico actualizado')
            ->assertJsonPath('datos.correo', 'tecnico.actualizado@ascensores.com');

        $this->patchJson("/api/v1/usuarios/{$usuarioId}/alternar-estado")
            ->assertOk()
            ->assertJsonPath('datos.activo', false);

        $this->assertDatabaseHas('usuarios', [
            'id' => $usuarioId,
            'nombre' => 'Técnico actualizado',
            'activo' => false,
        ]);
    }

    public function test_usuario_con_rol_cliente_requiere_cliente_asociado(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $rolCliente = Rol::where('nombre', 'cliente')->firstOrFail();
        $cliente = $this->crearCliente();

        $this->postJson('/api/v1/usuarios', [
            'nombre' => 'Portal',
            'correo' => 'portal@cliente.com',
            'password' => 'secreto1234',
            'rol_id' => $rolCliente->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['cliente_id'], 'errores');

        $this->postJson('/api/v1/usuarios', [
            'nombre' => 'Portal',
            'correo' => 'portal@cliente.com',
            'password' => 'secreto1234',
            'rol_id' => $rolCliente->id,
            'cliente_id' => $cliente->id,
        ])->assertCreated()->assertJsonPath('datos.cliente.nombre', $cliente->nombre);
    }

    public function test_crud_de_clientes_y_contactos(): void
    {
        $this->actingAs($this->administrador, 'sanctum');

        $respuesta = $this->postJson('/api/v1/clientes', [
            'tipo' => 'juridica',
            'nombre' => 'Edificio Mirador Plaza',
            'nit' => '900123456-1',
            'direccion' => 'Cra 15 # 100-20',
            'telefono' => '3001234567',
            'correo' => 'admin@miradorplaza.com',
        ]);

        $respuesta->assertCreated()
            ->assertJsonFragment(['nombre' => 'Edificio Mirador Plaza'])
            ->assertJsonStructure(['datos' => ['identificador_uuid']]);

        $clienteId = $respuesta->json('datos.id');

        $this->postJson("/api/v1/clientes/{$clienteId}/contactos", [
            'nombre' => 'Pedro Pérez',
            'cargo' => 'Administrador',
            'telefono' => '3109876543',
        ])->assertCreated()->assertJsonFragment(['nombre' => 'Pedro Pérez']);

        $this->getJson("/api/v1/clientes/{$clienteId}")
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Pedro Pérez']);

        $this->patchJson("/api/v1/clientes/{$clienteId}", ['direccion' => 'Nueva dirección'])
            ->assertOk()
            ->assertJsonPath('datos.direccion', 'Nueva dirección');

        // Los listados paginados usan claves en español.
        $this->getJson('/api/v1/clientes?por_pagina=500')
            ->assertOk()
            ->assertJsonStructure(['datos', 'enlaces' => ['siguiente'], 'metadatos' => ['total', 'pagina_actual', 'ultima_pagina']])
            ->assertJsonPath('metadatos.por_pagina', 100);
    }

    public function test_cliente_juridico_exige_nit_y_natural_exige_documento(): void
    {
        $this->actingAs($this->administrador, 'sanctum');

        $this->postJson('/api/v1/clientes', ['tipo' => 'juridica', 'nombre' => 'Sin NIT'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nit'], 'errores')
            ->assertJsonPath('mensaje', 'Los datos enviados no son válidos.');

        $this->postJson('/api/v1/clientes', ['tipo' => 'natural', 'nombre' => 'Sin documento'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['numero_documento'], 'errores');

        $this->crearCliente(['nit' => '900111222-3']);

        $this->postJson('/api/v1/clientes', ['tipo' => 'juridica', 'nombre' => 'Duplicado', 'nit' => '900111222-3'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nit'], 'errores');
    }

    public function test_crud_de_edificios(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $cliente = $this->crearCliente();

        $respuesta = $this->postJson('/api/v1/edificios', [
            'cliente_id' => $cliente->id,
            'nombre' => 'Torre Central',
            'direccion' => 'Calle 26 # 68-50',
            'ciudad' => 'Bogotá',
            'pisos' => 20,
            'nombre_contacto' => 'Luis Mora',
            'correo_contacto' => 'luis@torrecentral.co',
        ]);

        $respuesta->assertCreated()->assertJsonFragment(['nombre' => 'Torre Central']);
        $edificioId = $respuesta->json('datos.id');

        $this->patchJson("/api/v1/edificios/{$edificioId}", ['ciudad' => 'Medellín'])
            ->assertOk()
            ->assertJsonPath('datos.ciudad', 'Medellín');
    }

    public function test_edificio_exige_un_contacto(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $cliente = $this->crearCliente();

        $this->postJson('/api/v1/edificios', [
            'cliente_id' => $cliente->id,
            'nombre' => 'Sin contacto',
            'direccion' => 'Calle 1',
            'ciudad' => 'Cali',
        ])->assertUnprocessable()->assertJsonValidationErrors(['nombre_contacto', 'telefono_contacto'], 'errores');
    }

    public function test_edificio_con_equipos_activos_no_se_elimina(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $edificio = $this->crearEdificio($this->crearCliente());
        $edificio->equipos()->create(['codigo' => 'ASC-900', 'tipo' => 'ascensor', 'estado' => 'activo']);

        $this->deleteJson("/api/v1/edificios/{$edificio->id}")
            ->assertUnprocessable()
            ->assertJsonStructure(['mensaje']);

        $this->patchJson("/api/v1/edificios/{$edificio->id}", ['activo' => false])
            ->assertOk()
            ->assertJsonPath('datos.activo', false);

        $this->postJson('/api/v1/equipos', [
            'edificio_id' => $edificio->id,
            'codigo' => 'ASC-901',
            'ascensor' => $this->datosAscensor(),
        ])->assertUnprocessable();
    }

    public function test_registra_ascensor_con_ficha_tecnica_e_historial(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $edificio = $this->crearEdificio($this->crearCliente());

        $respuesta = $this->postJson('/api/v1/equipos', [
            'edificio_id' => $edificio->id,
            'codigo' => 'ASC-001',
            'tipo' => 'ascensor',
            'marca' => 'Otis',
            'modelo' => 'Gen2',
            'numero_serie' => 'OT-98765',
            'ubicacion' => 'Torre Norte - Fila A',
            'estado' => 'activo',
            'ascensor' => $this->datosAscensor(),
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('datos.codigo', 'ASC-001')
            ->assertJsonPath('datos.ascensor.paradas', 15)
            ->assertJsonPath('datos.ascensor.puertas_piso', 15)
            ->assertJsonPath('datos.ascensor.capacidad_kg', 1000);

        $equipoId = $respuesta->json('datos.id');

        $this->getJson("/api/v1/equipos/{$equipoId}/historial")
            ->assertOk()
            ->assertJsonFragment(['evento' => 'alta_equipo']);

        $this->postJson("/api/v1/equipos/{$equipoId}/historial", [
            'evento' => 'inspeccion_inicial',
            'descripcion' => 'Inspección de entrega satisfactoria.',
        ])->assertCreated()->assertJsonFragment(['evento' => 'inspeccion_inicial']);

        // Se puede actualizar el equipo enviando su mismo código (antes fallaba por la regla unique).
        $this->patchJson("/api/v1/equipos/{$equipoId}", [
            'codigo' => 'ASC-001',
            'estado' => 'mantenimiento',
            'ascensor' => ['capacidad_kg' => 1200],
        ])
            ->assertOk()
            ->assertJsonPath('datos.estado', 'mantenimiento')
            ->assertJsonPath('datos.ascensor.capacidad_kg', 1200);

        $this->getJson("/api/v1/equipos/{$equipoId}/historial")
            ->assertOk()
            ->assertJsonFragment(['evento' => 'cambio_estado'])
            ->assertJsonFragment(['evento' => 'ficha_tecnica_actualizada']);
    }

    public function test_ficha_del_ascensor_exige_valores_mayores_que_cero(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $edificio = $this->crearEdificio($this->crearCliente());

        $this->postJson('/api/v1/equipos', [
            'edificio_id' => $edificio->id,
            'codigo' => 'ASC-002',
            'ascensor' => ['capacidad_kg' => 0, 'paradas' => 5],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ascensor.capacidad_kg', 'ascensor.velocidad_mpm'], 'errores');
    }

    public function test_usuario_sin_permiso_no_accede_a_catalogos_protegidos(): void
    {
        $tecnico = Usuario::factory()->create([
            'rol_id' => Rol::where('nombre', 'tecnico')->value('id'),
        ]);

        $this->actingAs($tecnico, 'sanctum')
            ->getJson('/api/v1/usuarios')
            ->assertForbidden()
            ->assertJsonPath('mensaje', 'No tiene permiso para realizar esta acción.');
    }

    public function test_administrador_consulta_roles_permisos_y_auditoria(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $this->crearCliente();
        $this->postJson('/api/v1/clientes', ['tipo' => 'natural', 'nombre' => 'Ana', 'numero_documento' => '1020304050']);

        $this->getJson('/api/v1/roles')->assertOk();
        $this->getJson('/api/v1/permisos')
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'usuarios.ver']);
        $this->getJson('/api/v1/registros-auditoria?modulo=clientes')
            ->assertOk()
            ->assertJsonPath('datos.0.modulo', 'clientes')
            ->assertJsonPath('datos.0.usuario.id', $this->administrador->id)
            ->assertJsonStructure(['datos' => [['ip', 'accion', 'modulo', 'modelo', 'cambios', 'created_at']]]);
    }

    public function test_administrador_crea_roles_y_asigna_permisos(): void
    {
        $this->actingAs($this->administrador, 'sanctum');

        $respuesta = $this->postJson('/api/v1/roles', [
            'nombre' => 'auxiliar',
            'descripcion' => 'Consulta de clientes',
        ]);

        $respuesta->assertCreated()->assertJsonPath('datos.nombre', 'auxiliar');
        $rolId = $respuesta->json('datos.id');

        $this->patchJson("/api/v1/roles/{$rolId}", ['descripcion' => 'Auxiliar administrativo'])
            ->assertOk()
            ->assertJsonPath('datos.descripcion', 'Auxiliar administrativo');

        $permisos = $this->putJson("/api/v1/roles/{$rolId}/permisos", [
            'permisos' => ['edificios.ver', 'clientes.ver'],
        ])->assertOk()->json('datos.permisos');

        $this->assertContains('clientes.ver', $permisos);
        $this->assertContains('edificios.ver', $permisos);

        $this->assertDatabaseHas('registros_auditoria', [
            'accion' => 'permisos_actualizados',
            'modelo' => Rol::class,
            'modelo_id' => $rolId,
        ]);

        $this->assertDatabaseHas('rol_permisos', [
            'rol_id' => $rolId,
            'permiso_id' => Permiso::where('nombre', 'edificios.ver')->value('id'),
        ]);

        $this->putJson("/api/v1/roles/{$rolId}/permisos", ['permisos' => []])->assertOk();
    }

    public function test_permisos_exclusivos_solo_se_asignan_al_administrador(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $coordinador = Rol::where('nombre', 'coordinador')->firstOrFail();

        $this->putJson("/api/v1/roles/{$coordinador->id}/permisos", [
            'permisos' => ['clientes.ver', 'registros_auditoria.ver'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['permisos'], 'errores');

        $this->assertFalse($coordinador->tienePermiso('registros_auditoria.ver'));
        $this->assertFalse(Rol::where('nombre', 'supervisor')->first()->tienePermiso('usuarios.crear'));
    }

    public function test_rol_administrador_no_se_renombra_ni_cambia_permisos(): void
    {
        $this->actingAs($this->administrador, 'sanctum');

        $this->patchJson('/api/v1/roles/'.$this->rolAdministrador->id, ['nombre' => 'otro-admin'])
            ->assertForbidden();

        $this->putJson('/api/v1/roles/'.$this->rolAdministrador->id.'/permisos', ['permisos' => []])
            ->assertForbidden();
    }

    public function test_gestion_de_roles_y_catalogo_requiere_permisos(): void
    {
        $tecnico = Usuario::factory()->create([
            'rol_id' => Rol::where('nombre', 'tecnico')->value('id'),
        ]);

        $this->actingAs($tecnico, 'sanctum')
            ->getJson('/api/v1/permisos')
            ->assertForbidden();

        $this->postJson('/api/v1/roles', ['nombre' => 'no-autorizado'])->assertForbidden();
    }

    public function test_la_api_de_usuarios_no_permite_eliminar(): void
    {
        $this->actingAs($this->administrador, 'sanctum')
            ->deleteJson('/api/v1/usuarios/'.$this->administrador->id)
            ->assertMethodNotAllowed();
    }

    public function test_seeder_de_administrador_exige_contrasena_explicita(): void
    {
        config([
            'admin.correo' => 'admin@ascensores.test',
            'admin.password' => '',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->seed(AdministradorSeeder::class);
    }

    public function test_tablas_y_columnas_en_espanol(): void
    {
        foreach (['usuarios', 'permisos', 'rol_permisos', 'clientes', 'contactos_cliente', 'edificios', 'equipos', 'ascensores', 'historial_equipos', 'registros_auditoria'] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}");
        }

        $this->assertFalse(Schema::hasTable('puertas_electricas'));
        $this->assertFalse(Schema::hasTable('electric_doors'));

        $this->assertTrue(Schema::hasColumns('usuarios', ['nombre', 'correo', 'correo_verificado_en', 'rol_id', 'cliente_id', 'intentos_fallidos', 'bloqueado_hasta']));
        $this->assertTrue(Schema::hasColumns('registros_auditoria', ['usuario_id', 'accion', 'modulo', 'ip']));
        $this->assertTrue(Schema::hasColumns('ascensores', ['equipo_id', 'capacidad_kg', 'puertas_cabina', 'puertas_piso']));
        $this->assertTrue(Schema::hasColumns('edificios', ['cliente_id', 'activo']));
    }

    public function test_migracion_de_columna_de_verificacion_es_reversible(): void
    {
        $usuario = Usuario::factory()->create();

        DB::table('usuarios')
            ->where('id', $usuario->id)
            ->update(['correo_verificado_en' => now()->startOfSecond()]);

        $migracion = require database_path(
            'migrations/2026_10_09_030000_translate_user_verification_column_to_spanish.php'
        );

        $migracion->down();
        $this->assertTrue(Schema::hasColumn('usuarios', 'email_verified_at'));

        $migracion->up();
        $this->assertTrue(Schema::hasColumn('usuarios', 'correo_verificado_en'));
        $this->assertFalse(Schema::hasColumn('usuarios', 'email_verified_at'));
    }
}
