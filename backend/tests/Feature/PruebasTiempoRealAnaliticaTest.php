<?php

namespace Tests\Feature;

use App\Events\RegistroAuditoriaCreado;
use App\Events\UbicacionTecnicoActualizada;
use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\Periodicidad;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\EspecialidadSeeder;
use Database\Seeders\PeriodicidadSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as SolicitudHttp;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Ubicación en vivo, auditoría en tiempo real, analítica con Python y credenciales por rol. */
class PruebasTiempoRealAnaliticaTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $administrador;

    private Usuario $tecnico;

    private Usuario $usuarioCliente;

    private Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:00:00'));
        Notification::fake();
        $this->seed([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class, EspecialidadSeeder::class, ChecklistSeeder::class]);
        $rol = fn ($nombre) => Rol::where('nombre', $nombre)->value('id');
        $this->administrador = Usuario::factory()->create(['rol_id' => $rol('administrador'), 'activo' => true, 'password' => 'AdminClave123']);
        $cliente = Cliente::factory()->create();
        $this->usuarioCliente = Usuario::factory()->create(['rol_id' => $rol('cliente'), 'activo' => true, 'cliente_id' => $cliente->id]);
        $this->equipo = Equipo::factory()->create(['edificio_id' => Edificio::factory()->create(['cliente_id' => $cliente->id])->id, 'estado' => 'activo']);

        $this->actingAs($this->administrador, 'sanctum');
        $usuarioId = $this->postJson('/api/v1/tecnicos', [
            'nombre' => 'Andrés Rojas', 'correo' => 'andres@sigma.test', 'password' => 'Clave1234', 'tipo_documento' => 'CC', 'numero_documento' => '10203040',
            'especialidades' => [Especialidad::value('id')],
        ])->assertCreated()->json('datos.usuario_id');
        $this->tecnico = Usuario::findOrFail($usuarioId);
        $this->postJson('/api/v1/asignaciones', [
            'equipo_id' => $this->equipo->id, 'periodicidad_id' => Periodicidad::where('meses', 1)->value('id'), 'fecha_inicio' => '2026-10-20',
        ])->assertCreated();
    }

    public function test_el_tecnico_envia_su_ubicacion_y_auditoria_la_ve_en_vivo(): void
    {
        Event::fake([UbicacionTecnicoActualizada::class]);
        $this->actingAs($this->tecnico, 'sanctum');
        $orden = $this->postJson("/api/v1/qr/{$this->equipo->codigo_qr}/mantenimiento")->assertCreated()->json('datos');

        $this->postJson("/api/v1/ordenes/{$orden['id']}/ubicacion", ['latitud' => 4.7110, 'longitud' => -74.0721, 'precision_metros' => 12])
            ->assertCreated()->assertJsonPath('datos.tecnico', 'Andrés Rojas');
        Event::assertDispatched(UbicacionTecnicoActualizada::class);
        $this->postJson("/api/v1/ordenes/{$orden['id']}/ubicacion", ['latitud' => 120, 'longitud' => 0])->assertStatus(422);

        // Otros roles no envían ubicación ni ven el mapa sin permiso.
        $this->actingAs($this->usuarioCliente, 'sanctum');
        $this->postJson("/api/v1/ordenes/{$orden['id']}/ubicacion", ['latitud' => 4.7, 'longitud' => -74])->assertForbidden();
        $this->getJson('/api/v1/auditoria/en-vivo')->assertForbidden();

        $this->actingAs($this->administrador, 'sanctum');
        $activos = $this->getJson('/api/v1/auditoria/en-vivo')->assertOk()->json('datos');
        $this->assertCount(1, $activos);
        $this->assertSame(4.711, $activos[0]['latitud']);
        $this->assertSame($this->equipo->codigo, $activos[0]['equipo']);
    }

    public function test_cada_registro_de_auditoria_se_anuncia_en_vivo(): void
    {
        Event::fake([RegistroAuditoriaCreado::class]);
        RegistroAuditoria::create(['usuario_id' => $this->administrador->id, 'accion' => 'creado', 'modulo' => 'clientes', 'modelo' => Cliente::class, 'modelo_id' => 1]);
        Event::assertDispatched(RegistroAuditoriaCreado::class, fn ($e) => $e->broadcastAs() === 'registro.creado' && $e->broadcastOn()[0]->name === 'private-auditoria');
    }

    public function test_auditoria_muestra_lo_reciente_y_busca_en_el_historial(): void
    {
        $viejo = tap((new RegistroAuditoria)->forceFill(['usuario_id' => $this->tecnico->id, 'accion' => 'creado', 'modulo' => 'clientes', 'modelo' => Cliente::class, 'modelo_id' => 1, 'created_at' => now()->subDays(3)]))->save();
        $recientes = collect($this->getJson('/api/v1/registros-auditoria?recientes_horas=24&por_pagina=100')->assertOk()->json('datos'))->pluck('id');
        $this->assertNotContains($viejo->id, $recientes);
        $this->assertNotEmpty($recientes);

        $encontrados = collect($this->getJson('/api/v1/registros-auditoria?busqueda=andrés&por_pagina=100')->assertOk()->json('datos'))->pluck('id');
        $this->assertContains($viejo->id, $encontrados);
    }

    public function test_la_analitica_se_calcula_en_python_con_la_clave_de_servicio(): void
    {
        config(['services.analitica.url' => 'http://analitica.test', 'services.analitica.clave' => 'clave-servicio']);
        Http::fake(['analitica.test/*' => Http::response(['meses' => [], 'totales' => ['emergencias' => 0], 'periodo' => ['meses' => 12]])]);

        $this->getJson('/api/v1/analitica/resumen')->assertOk()->assertJsonPath('datos.periodo.meses', 12);
        Http::assertSent(fn (SolicitudHttp $s) => $s->hasHeader('X-Api-Key', 'clave-servicio')
            && $s->url() === 'http://analitica.test/api/v1/indicadores/resumen'
            && $s['equipos'][0]['codigo'] === $this->equipo->codigo
            && ! array_key_exists('correo', $s['equipos'][0]));

        // El cliente solo envía a Python los ascensores de su cliente.
        $otro = Equipo::factory()->create();
        Http::fake(['analitica.test/*' => Http::response(['pronostico' => [], 'metodo' => 'x', 'riesgo' => []])]);
        $this->actingAs($this->usuarioCliente, 'sanctum');
        $this->getJson('/api/v1/analitica/predicciones')->assertOk();
        Http::assertSent(fn (SolicitudHttp $s) => collect($s['equipos'])->pluck('id')->doesntContain($otro->id));

        // Si el servicio no responde, mensaje claro.
        Http::fake(['caido.test/*' => Http::response(null, 500)]);
        config(['services.analitica.url' => 'http://caido.test']);
        $this->actingAs($this->administrador, 'sanctum');
        $this->getJson('/api/v1/analitica/resumen')->assertStatus(422);
    }

    public function test_solo_el_administrador_ve_las_credenciales_de_cada_rol(): void
    {
        $rolAdmin = $this->administrador->rol_id;
        $this->getJson('/api/v1/roles/credenciales')->assertOk()->assertJsonFragment(['rol_id' => $rolAdmin, 'correo' => $this->administrador->correo]);
        $this->getJson("/api/v1/roles/{$rolAdmin}/credencial")->assertOk()->assertJsonPath('datos.contrasena', 'AdminClave123');
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'credencial_consultada']);
        // La copia cifrada nunca sale en las respuestas de usuarios.
        $this->assertArrayNotHasKey('contrasena_cifrada', $this->administrador->fresh()->toArray());

        $this->actingAs($this->tecnico, 'sanctum');
        $this->getJson('/api/v1/roles/credenciales')->assertForbidden();
        $this->getJson("/api/v1/roles/{$rolAdmin}/credencial")->assertForbidden();
    }
}
