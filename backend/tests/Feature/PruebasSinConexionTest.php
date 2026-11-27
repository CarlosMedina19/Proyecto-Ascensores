<?php

namespace Tests\Feature;

use App\Models\ChecklistSeccion;
use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Emergencia;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\OrdenTrabajo;
use App\Models\Rol;
use App\Models\Tecnico;
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
use Tests\TestCase;

/** La app del técnico guarda los cambios sin conexión y los envía después: hora real y sin duplicados. */
class PruebasSinConexionTest extends TestCase
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
        $this->seed([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class, EspecialidadSeeder::class, ChecklistSeeder::class]);
        $this->administrador = Usuario::factory()->create(['rol_id' => Rol::where('nombre', 'administrador')->value('id'), 'activo' => true]);
        $cliente = Cliente::factory()->create();
        $this->equipo = Equipo::factory()->create(['edificio_id' => Edificio::factory()->create(['cliente_id' => $cliente->id])->id, 'estado' => 'activo']);

        $this->actingAs($this->administrador, 'sanctum');
        $usuarioId = $this->postJson('/api/v1/tecnicos', [
            'nombre' => 'Técnico SIGMA', 'correo' => 'tecnico@sigma.test', 'password' => 'Clave1234', 'tipo_documento' => 'CC', 'numero_documento' => '10203040',
            'especialidades' => [Especialidad::value('id')],
        ])->assertCreated()->json('datos.usuario_id');
        $this->tecnico = Usuario::findOrFail($usuarioId);
    }

    private function ordenAsignada(): OrdenTrabajo
    {
        $this->actingAs($this->administrador, 'sanctum');
        $id = $this->postJson('/api/v1/ordenes', [
            'servicio' => 'correctivo', 'cliente_id' => $this->equipo->edificio->cliente_id, 'edificio_id' => $this->equipo->edificio_id,
            'equipo_id' => $this->equipo->id, 'fecha' => '2026-11-23', 'hora' => '10:00', 'duracion_estimada' => 90, 'descripcion' => 'Ruido en la cabina',
        ])->assertCreated()->json('datos.id');
        $this->putJson("/api/v1/ordenes/{$id}/tecnicos", ['tecnicos' => [Tecnico::where('usuario_id', $this->tecnico->id)->value('id')]])->assertOk();

        return OrdenTrabajo::findOrFail($id);
    }

    public function test_los_cambios_de_estado_guardados_sin_conexion_conservan_su_hora_real(): void
    {
        $orden = $this->ordenAsignada();
        $this->actingAs($this->tecnico, 'sanctum');

        // Sin conexión: en camino a las 09:40 e inicio a las 10:05. Se envían juntos a las 11:00.
        $this->travelTo(CarbonImmutable::parse('2026-11-23 11:00:00'));
        $this->patchJson("/api/v1/ordenes/{$orden->id}/estado", ['estado' => 'en_camino', 'ocurrido_en' => '2026-11-23T09:40:00-05:00'])->assertOk();
        $this->patchJson("/api/v1/ordenes/{$orden->id}/estado", ['estado' => 'en_proceso', 'ocurrido_en' => '2026-11-23T10:05:00-05:00'])
            ->assertOk()->assertJsonPath('datos.estado', 'en_proceso');

        $orden->refresh();
        $this->assertSame('2026-11-23 10:05', $orden->inicio_en->format('Y-m-d H:i'));
        $traza = $orden->trazabilidad()->where('accion', 'estado_cambiado')->where('valor_nuevo', 'en_proceso')->first();
        $this->assertStringContainsString('Registrado sin conexión el 23/11/2026 10:05', (string) $traza->detalle);

        // Pendiente con motivo: el motivo y la nota quedan juntos.
        $this->patchJson("/api/v1/ordenes/{$orden->id}/estado", ['estado' => 'pendiente', 'motivo' => 'Falta un repuesto', 'ocurrido_en' => '2026-11-23T10:40:00-05:00'])->assertOk();
        $this->assertStringStartsWith('Falta un repuesto Registrado sin conexión', (string) $orden->trazabilidad()->where('valor_nuevo', 'pendiente')->value('detalle'));

        // Sin hora: como siempre, la del servidor y sin nota.
        $this->patchJson("/api/v1/ordenes/{$orden->id}/estado", ['estado' => 'en_proceso'])->assertOk();
        $this->assertNull($orden->trazabilidad()->where('valor_nuevo', 'en_proceso')->latest('id')->value('detalle'));
    }

    public function test_una_hora_futura_usa_la_del_servidor_y_una_de_hace_mas_de_siete_dias_se_rechaza(): void
    {
        $orden = $this->ordenAsignada();
        $this->actingAs($this->tecnico, 'sanctum');

        $this->patchJson("/api/v1/ordenes/{$orden->id}/estado", ['estado' => 'en_camino', 'ocurrido_en' => '2026-11-10T09:00:00-05:00'])->assertStatus(422);
        $this->assertSame('asignada', $orden->fresh()->estado);

        // Reloj del celular adelantado: se usa la hora del servidor (09:00), sin nota de «sin conexión».
        $this->patchJson("/api/v1/ordenes/{$orden->id}/estado", ['estado' => 'en_camino'])->assertOk();
        $this->patchJson("/api/v1/ordenes/{$orden->id}/estado", ['estado' => 'en_proceso', 'ocurrido_en' => '2026-11-23T12:00:00-05:00'])->assertOk();
        $this->assertSame('2026-11-23 09:00', $orden->fresh()->inicio_en->format('Y-m-d H:i'));
    }

    public function test_la_llegada_a_una_emergencia_usa_la_hora_en_que_ocurrio(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $id = $this->postJson('/api/v1/emergencias', ['equipo_id' => $this->equipo->id, 'tipo' => 'falla'])->assertCreated()->json('datos.id');

        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:00:00'));
        $this->actingAs($this->tecnico, 'sanctum');
        $this->postJson("/api/v1/emergencias/{$id}/estado", ['estado' => 'en_sitio', 'ocurrido_en' => '2026-11-23T09:25:00-05:00'])->assertOk();
        $emergencia = Emergencia::findOrFail($id);
        $this->assertSame('2026-11-23 09:25', $emergencia->llegada_en->format('Y-m-d H:i'));
        $this->assertSame(25, $emergencia->minutosRespuesta());

        // Una solución con hora anterior a la llegada no se acepta: queda la de la llegada.

        $this->postJson("/api/v1/emergencias/{$id}/estado", ['estado' => 'resuelta', 'solucion' => 'Se ajustó el operador de puertas.', 'ocurrido_en' => '2026-11-23T09:10:00-05:00'])->assertOk();
        $this->assertSame('2026-11-23 09:25', $emergencia->fresh()->resuelta_en->format('Y-m-d H:i'));
    }

    public function test_un_envio_repetido_con_la_misma_clave_no_se_aplica_dos_veces(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $antes = ChecklistSeccion::count();
        $cabeceras = ['X-Clave-Envio' => 'b6f2c1d0-7a3e-4c55-9d1f-0a1b2c3d4e5f'];

        $primera = $this->postJson('/api/v1/planilla/secciones', ['nombre' => 'Sala de bombas'], $cabeceras)->assertCreated();
        $segunda = $this->postJson('/api/v1/planilla/secciones', ['nombre' => 'Sala de bombas'], $cabeceras)->assertCreated()->assertHeader('X-Envio-Repetido', '1');
        $this->assertSame($primera->json('datos'), $segunda->json('datos'));
        $this->assertSame($antes + 1, ChecklistSeccion::count());

        // Otra clave sí es otro envío.
        $this->postJson('/api/v1/planilla/secciones', ['nombre' => 'Sala de bombas'], ['X-Clave-Envio' => 'otra-clave-1234'])->assertCreated();
        $this->assertSame($antes + 2, ChecklistSeccion::count());

        // La clave es por usuario: otro usuario con la misma clave no recibe la respuesta guardada.
        $otro = Usuario::factory()->create(['rol_id' => Rol::where('nombre', 'administrador')->value('id'), 'activo' => true]);
        $this->actingAs($otro, 'sanctum')->postJson('/api/v1/planilla/secciones', ['nombre' => 'Cuarto de bombas'], $cabeceras)->assertCreated()->assertHeaderMissing('X-Envio-Repetido');
    }
}
