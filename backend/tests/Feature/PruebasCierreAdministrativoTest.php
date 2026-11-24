<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\MantenimientoProgramado;
use App\Models\OrdenTrabajo;
use App\Models\Periodicidad;
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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Cierre administrativo de órdenes que quedaron abiertas (Auditoría · Técnicos en mantenimiento). */
class PruebasCierreAdministrativoTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $administrador;

    private Usuario $tecnico;

    private Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00:00'));
        Notification::fake();
        Storage::fake('local');
        $this->seed([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class, EspecialidadSeeder::class, ChecklistSeeder::class]);
        $this->administrador = $this->usuario('administrador');
        $cliente = Cliente::factory()->create();
        $this->equipo = Equipo::factory()->create(['edificio_id' => Edificio::factory()->create(['cliente_id' => $cliente->id])->id, 'estado' => 'activo']);

        $this->actingAs($this->administrador, 'sanctum');
        $usuarioId = $this->postJson('/api/v1/tecnicos', [
            'nombre' => 'Técnico SIGMA', 'correo' => 'tecnico@sigma.test', 'password' => 'Clave1234', 'tipo_documento' => 'CC', 'numero_documento' => '10203040',
            'especialidades' => [Especialidad::value('id')],
        ])->assertCreated()->json('datos.usuario_id');
        $this->tecnico = Usuario::findOrFail($usuarioId);
        $this->postJson('/api/v1/asignaciones', [
            'equipo_id' => $this->equipo->id, 'periodicidad_id' => Periodicidad::where('meses', 1)->value('id'), 'fecha_inicio' => '2026-11-03',
        ])->assertCreated();
    }

    private function usuario(string $rol): Usuario
    {
        return Usuario::factory()->create(['rol_id' => Rol::where('nombre', $rol)->value('id'), 'activo' => true]);
    }

    /** El técnico inicia el mantenimiento desde el QR y no lo cierra en la app. */
    private function ordenAbierta(): int
    {
        $this->actingAs($this->tecnico, 'sanctum');
        $orden = $this->postJson("/api/v1/qr/{$this->equipo->codigo_qr}/mantenimiento")->assertCreated()->json('datos');
        $this->postJson("/api/v1/ordenes/{$orden['id']}/ubicacion", ['latitud' => 4.711, 'longitud' => -74.072])->assertCreated();

        return $orden['id'];
    }

    public function test_el_coordinador_ve_los_tecnicos_en_mantenimiento_y_finaliza_una_orden_abierta(): void
    {
        $id = $this->ordenAbierta();
        $coordinador = $this->usuario('coordinador');
        $this->actingAs($coordinador, 'sanctum');

        $this->assertCount(1, $this->getJson('/api/v1/auditoria/en-vivo')->assertOk()->json('datos'));
        $this->getJson('/api/v1/registros-auditoria')->assertForbidden();

        $this->patchJson("/api/v1/ordenes/{$id}/cerrar", ['motivo' => 'ok'])->assertStatus(422);
        $this->patchJson("/api/v1/ordenes/{$id}/cerrar", ['motivo' => 'El técnico terminó, pero no cerró la orden en la app.'])
            ->assertOk()->assertJsonPath('datos.estado', 'finalizada');

        $orden = OrdenTrabajo::findOrFail($id);
        $this->assertStringContainsString('Cierre administrativo', $orden->informe);
        $this->assertNull($orden->duracion_minutos, 'no se inventa una duración');
        $this->assertNotNull($orden->fin_en);
        $this->assertTrue($orden->trazabilidad()->where('accion', 'cierre_administrativo')->exists());
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'cierre_administrativo', 'usuario_id' => $coordinador->id]);
        if ($orden->mantenimiento_id) {
            $this->assertSame('ejecutado', MantenimientoProgramado::find($orden->mantenimiento_id)->estado);
        }

        // Ya no aparece en el mapa, no se puede cerrar dos veces y el técnico deja de enviar su ubicación.
        $this->assertCount(0, $this->getJson('/api/v1/auditoria/en-vivo')->json('datos'));
        $this->patchJson("/api/v1/ordenes/{$id}/cerrar", ['motivo' => 'Segundo intento de cierre.'])->assertStatus(422);
        $this->actingAs($this->tecnico, 'sanctum');
        $this->postJson("/api/v1/ordenes/{$id}/ubicacion", ['latitud' => 4.7, 'longitud' => -74])->assertStatus(422);
    }

    public function test_solo_administrador_y_coordinador_cierran_ordenes(): void
    {
        $id = $this->ordenAbierta();

        $this->actingAs($this->tecnico, 'sanctum');
        $this->patchJson("/api/v1/ordenes/{$id}/cerrar", ['motivo' => 'Lo cierro yo mismo sin firmas.'])->assertForbidden();

        $this->actingAs($this->usuario('supervisor'), 'sanctum');
        $this->getJson('/api/v1/auditoria/en-vivo')->assertForbidden();
        $this->patchJson("/api/v1/ordenes/{$id}/cerrar", ['motivo' => 'El supervisor solo consulta.'])->assertForbidden();

        $this->actingAs($this->administrador, 'sanctum');
        $this->patchJson("/api/v1/ordenes/{$id}/cerrar", ['motivo' => 'Prueba del administrador.'])->assertOk();
    }

    public function test_una_orden_que_no_ha_empezado_se_cancela_no_se_cierra(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $id = $this->postJson('/api/v1/ordenes', [
            'servicio' => 'correctivo', 'equipo_id' => $this->equipo->id, 'cliente_id' => $this->equipo->edificio->cliente_id,
            'edificio_id' => $this->equipo->edificio_id, 'fecha' => '2026-11-24', 'hora' => '10:00', 'prioridad' => 'media',
            'descripcion' => 'Ruido en la cabina.',
        ])->assertCreated()->json('datos.id');

        $this->patchJson("/api/v1/ordenes/{$id}/cerrar", ['motivo' => 'Cerrar una orden programada.'])
            ->assertStatus(422)->assertJsonPath('mensaje', 'Solo se cierran órdenes en camino, en proceso o pendientes. Las que aún no empiezan se cancelan.');
    }
}
