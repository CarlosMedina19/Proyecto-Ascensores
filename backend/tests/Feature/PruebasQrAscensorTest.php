<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\Periodicidad;
use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\OrdenTrabajoNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\EspecialidadSeeder;
use Database\Seeders\PeriodicidadSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** QR del ascensor: estado para cualquier persona; el técnico inicia el mantenimiento y se avisa al iniciar y al finalizar. */
class PruebasQrAscensorTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $administrador;

    private Usuario $tecnico;

    private Usuario $usuarioCliente;

    private Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:00:00')); // lunes
        Notification::fake();
        $this->seed([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class, EspecialidadSeeder::class, ChecklistSeeder::class]);
        $rol = fn ($nombre) => Rol::where('nombre', $nombre)->value('id');
        $this->administrador = Usuario::factory()->create(['rol_id' => $rol('administrador'), 'activo' => true]);
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

    public function test_cualquier_persona_ve_solo_el_estado_del_ascensor(): void
    {
        $this->app['auth']->forgetGuards();
        $datos = $this->getJson("/api/v1/publico/ascensores/{$this->equipo->codigo_qr}")->assertOk()->json('datos');
        $this->assertSame('activo', $datos['estado']);
        $this->assertSame($this->equipo->codigo, $datos['codigo']);
        $this->assertArrayNotHasKey('equipo_id', $datos);
        $this->assertNotNull($datos['proximo_mantenimiento']);

        $this->getJson("/api/v1/qr/{$this->equipo->codigo_qr}")->assertUnauthorized();
        $this->postJson("/api/v1/qr/{$this->equipo->codigo_qr}/mantenimiento")->assertUnauthorized();
    }

    public function test_el_tecnico_inicia_el_mantenimiento_desde_el_qr_y_se_avisa_al_iniciar_y_al_finalizar(): void
    {
        $this->actingAs($this->tecnico, 'sanctum');
        $this->getJson("/api/v1/qr/{$this->equipo->codigo_qr}")->assertOk()->assertJsonPath('datos.equipo_id', $this->equipo->id)->assertJsonPath('datos.orden', null);

        $orden = $this->postJson("/api/v1/qr/{$this->equipo->codigo_qr}/mantenimiento")->assertCreated()->assertJsonPath('datos.estado', 'en_proceso')->json('datos');
        // La orden sale de la visita programada más próxima y queda a nombre del técnico.
        $this->assertNotNull(\App\Models\OrdenTrabajo::find($orden['id'])->mantenimiento_id);
        Notification::assertSentTo($this->administrador, OrdenTrabajoNotification::class, fn ($n) => $n->evento === 'iniciada' && str_contains($n->mensaje(), 'Andrés Rojas'));

        // Escanear de nuevo continúa la misma orden.
        $this->postJson("/api/v1/qr/{$this->equipo->codigo_qr}/mantenimiento")->assertCreated()->assertJsonPath('datos.id', $orden['id']);

        // Al finalizar, el administrador y el cliente reciben el reporte.
        app(\App\Services\OrdenTrabajoService::class);
        $servicio = new \ReflectionMethod(\App\Services\OrdenTrabajoService::class, 'alFinalizar');
        $modelo = \App\Models\OrdenTrabajo::find($orden['id']);
        $modelo->update(['estado' => 'finalizada', 'fin_en' => now(), 'duracion_minutos' => 45, 'informe' => 'Lubricación y ajuste de frenos.']);
        $servicio->invoke(app(\App\Services\OrdenTrabajoService::class), $modelo->fresh());
        Notification::assertSentTo($this->administrador, OrdenTrabajoNotification::class, fn ($n) => $n->evento === 'finalizada');
        Notification::assertSentTo($this->usuarioCliente, OrdenTrabajoNotification::class, fn ($n) => $n->evento === 'finalizada' && str_contains($n->mensaje(), 'Lubricación'));
    }

    public function test_solo_un_tecnico_inicia_el_mantenimiento(): void
    {
        $this->actingAs($this->usuarioCliente, 'sanctum');
        $this->getJson("/api/v1/qr/{$this->equipo->codigo_qr}")->assertForbidden();
        $this->postJson("/api/v1/qr/{$this->equipo->codigo_qr}/mantenimiento")->assertStatus(422);
    }
}
