<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\MantenimientoProgramado;
use App\Models\OrdenTrabajo;
use App\Models\Periodicidad;
use App\Models\Rol;
use App\Models\Tecnico;
use App\Models\TrazabilidadOrden;
use App\Models\Usuario;
use App\Notifications\OrdenTrabajoNotification;
use App\Services\PlanService;
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
use LogicException;
use Tests\TestCase;

/**
 * Pruebas funcionales de la fase 3: técnicos, agenda y órdenes de trabajo (RF-022 a RF-029).
 * La fecha actual se fija en el lunes 5 de enero de 2026 a las 10:00.
 */
class PruebasFaseTresTest extends TestCase
{
    use RefreshDatabase;

    protected Usuario $administrador;

    protected Usuario $coordinador;

    protected Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-01-05 10:00:00'));
        Notification::fake();
        Storage::fake('local');
        $this->seed([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class, EspecialidadSeeder::class, ChecklistSeeder::class]);

        $this->administrador = $this->usuarioConRol('administrador');
        $this->coordinador = $this->usuarioConRol('coordinador');
        $this->equipo = $this->crearEquipo();
    }

    private function usuarioConRol(string $rol, array $datos = []): Usuario
    {
        return Usuario::factory()->create($datos + ['rol_id' => Rol::where('nombre', $rol)->value('id'), 'activo' => true]);
    }

    private function crearEquipo(?Cliente $cliente = null): Equipo
    {
        $edificio = Edificio::factory()->create(['cliente_id' => ($cliente ?? Cliente::factory()->create())->id]);

        return Equipo::factory()->create(['edificio_id' => $edificio->id]);
    }

    private function crearTecnico(string $nombre = 'Andrés Rojas', array $perfil = []): Tecnico
    {
        $usuario = $this->usuarioConRol('tecnico', ['nombre' => $nombre]);
        $tecnico = Tecnico::create($perfil + [
            'usuario_id' => $usuario->id,
            'tipo_documento' => 'CC',
            'numero_documento' => (string) fake()->unique()->numerify('10#######'),
            'disponibilidad' => 'disponible',
        ]);
        $tecnico->especialidades()->attach(Especialidad::first());

        return $tecnico->load('usuario');
    }

    private function datosOrden(array $datos = [], ?Equipo $equipo = null): array
    {
        $equipo ??= $this->equipo;

        return $datos + [
            'servicio' => 'correctivo',
            'cliente_id' => $equipo->edificio->cliente_id,
            'edificio_id' => $equipo->edificio_id,
            'equipo_id' => $equipo->id,
            'fecha' => '2026-01-06',
            'hora' => '08:00',
            'duracion_estimada' => 90,
            'descripcion' => 'Ruido en la cabina',
        ];
    }

    private function crearOrden(array $datos = [], ?Equipo $equipo = null): OrdenTrabajo
    {
        $id = $this->actingAs($this->coordinador, 'sanctum')
            ->postJson('/api/v1/ordenes', $this->datosOrden($datos, $equipo))
            ->assertCreated()
            ->json('datos.id');

        return OrdenTrabajo::findOrFail($id);
    }

    /** Fase 4: firmas del técnico y de quien recibe (requisito para finalizar, RF-025). */
    private function firmar(int $ordenId): void
    {
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        foreach (['tecnico' => 'Andrés Rojas', 'cliente' => 'Laura Gómez'] as $tipo => $nombre) {
            $this->postJson("/api/v1/ordenes/{$ordenId}/firmas", ['tipo' => $tipo, 'nombre' => $nombre, 'imagen' => $png])->assertCreated();
        }
    }

    /** Fase 4: todos los ítems de la planilla en Bueno. */
    private function completarPlanilla(int $ordenId): void
    {
        $this->putJson("/api/v1/ordenes/{$ordenId}/planilla", [
            'resultados' => ChecklistItem::all()->map(fn ($i) => ['item_id' => $i->id, 'resultado' => 'INS', 'estado' => 'B'])->all(),
        ])->assertOk();
    }

    // ---------------------------------------------------------------- RF-022 Técnicos

    public function test_el_administrador_registra_tecnicos_con_documento_unico_y_especialidades(): void
    {
        $datos = [
            'nombre' => 'Diana Mora',
            'correo' => 'diana.mora@sigma.test',
            'password' => 'Clave2026',
            'tipo_documento' => 'CC',
            'numero_documento' => '1110450003',
            'telefono' => '3003456789',
            'especialidades' => [Especialidad::first()->id],
        ];

        $this->actingAs($this->coordinador, 'sanctum')->postJson('/api/v1/tecnicos', $datos)->assertForbidden();

        $this->actingAs($this->administrador, 'sanctum')
            ->postJson('/api/v1/tecnicos', ['especialidades' => []] + $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['especialidades'], 'errores');

        $this->postJson('/api/v1/tecnicos', $datos)
            ->assertCreated()
            ->assertJsonPath('datos.nombre', 'Diana Mora')
            ->assertJsonPath('datos.perfil_completo', true)
            ->assertJsonPath('datos.puede_recibir_ordenes', true);

        $this->assertTrue(Usuario::where('correo', 'diana.mora@sigma.test')->first()->esTecnico());

        $this->postJson('/api/v1/tecnicos', ['correo' => 'otra@sigma.test'] + $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['numero_documento'], 'errores');
    }

    public function test_el_coordinador_actualiza_disponibilidad_y_un_no_disponible_no_recibe_ordenes(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden();

        $this->putJson("/api/v1/tecnicos/{$tecnico->usuario_id}/perfil", ['disponibilidad' => 'no_disponible'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo_no_disponible'], 'errores');

        $this->putJson("/api/v1/tecnicos/{$tecnico->usuario_id}/perfil", [
            'disponibilidad' => 'no_disponible',
            'motivo_no_disponible' => 'Vacaciones',
            'no_disponible_hasta' => '2026-01-20',
        ])->assertOk()->assertJsonPath('datos.puede_recibir_ordenes', false);

        $this->putJson("/api/v1/ordenes/{$orden->id}/tecnicos", ['tecnicos' => [$tecnico->id]])
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', 'Andrés Rojas no está disponible (Vacaciones).');

        $this->getJson('/api/v1/tecnicos/disponibilidad?fecha=2026-01-06&hora=08:00&duracion=90')
            ->assertOk()
            ->assertJsonPath('datos.0.asignable', false)
            ->assertJsonPath('datos.0.motivo', 'No disponible: Vacaciones');
    }

    // ---------------------------------------------------------------- RF-025 y RF-027

    public function test_la_orden_tiene_numero_consecutivo_y_requiere_datos_coherentes(): void
    {
        $primera = $this->crearOrden();
        $segunda = $this->crearOrden(['hora' => '13:00']);

        $this->assertSame('OT-2026-00001', $primera->numero);
        $this->assertSame('OT-2026-00002', $segunda->numero);
        $this->assertSame('media', $primera->prioridad);
        $this->assertSame('programada', $primera->estado);

        $otro = $this->crearEquipo();
        $this->postJson('/api/v1/ordenes', $this->datosOrden(['equipo_id' => $otro->id]))
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', 'El ascensor no pertenece al edificio indicado.');

        $this->postJson('/api/v1/ordenes', $this->datosOrden(['fecha' => '2026-01-02']))->assertUnprocessable();
        $this->postJson('/api/v1/ordenes', $this->datosOrden(['prioridad' => 'urgente']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['prioridad'], 'errores');
        $this->postJson('/api/v1/ordenes', ['servicio' => 'correctivo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cliente_id', 'edificio_id', 'equipo_id', 'fecha', 'hora'], 'errores');
    }

    public function test_prioridad_critica_avisa_al_coordinador_y_solo_la_cambia_coordinacion(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden(['tecnicos' => [$tecnico->id]]);

        $this->actingAs($tecnico->usuario, 'sanctum')
            ->patchJson("/api/v1/ordenes/{$orden->id}/prioridad", ['prioridad' => 'critica'])
            ->assertForbidden();

        $this->actingAs($this->coordinador, 'sanctum')
            ->patchJson("/api/v1/ordenes/{$orden->id}/prioridad", ['prioridad' => 'critica', 'motivo' => 'Personas atrapadas'])
            ->assertOk()
            ->assertJsonPath('datos.prioridad', 'critica');

        Notification::assertSentTo($this->coordinador, OrdenTrabajoNotification::class, fn ($n) => $n->evento === 'critica');
        $this->assertDatabaseHas('trazabilidad_ordenes', [
            'orden_id' => $orden->id, 'campo' => 'prioridad', 'valor_anterior' => 'media', 'valor_nuevo' => 'critica', 'usuario_id' => $this->coordinador->id,
        ]);
    }

    // ---------------------------------------------------------------- RF-023 y RF-024

    public function test_asignar_tecnicos_pasa_la_orden_a_asignada_y_notifica(): void
    {
        $andres = $this->crearTecnico();
        $diana = $this->crearTecnico('Diana Mora');
        $orden = $this->crearOrden();

        $this->putJson("/api/v1/ordenes/{$orden->id}/tecnicos", ['tecnicos' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tecnicos'], 'errores');

        $this->putJson("/api/v1/ordenes/{$orden->id}/tecnicos", ['tecnicos' => [$andres->id, $diana->id]])
            ->assertOk()
            ->assertJsonPath('datos.estado', 'asignada')
            ->assertJsonCount(2, 'datos.tecnicos');

        Notification::assertSentTo($andres->usuario, OrdenTrabajoNotification::class, fn ($n) => $n->evento === 'asignada');
        Notification::assertSentTo($diana->usuario, OrdenTrabajoNotification::class);
        $this->assertDatabaseHas('trazabilidad_ordenes', ['orden_id' => $orden->id, 'campo' => 'estado', 'valor_anterior' => 'programada', 'valor_nuevo' => 'asignada']);
    }

    public function test_un_tecnico_no_tiene_dos_ordenes_que_se_crucen(): void
    {
        $tecnico = $this->crearTecnico();
        $this->crearOrden(['hora' => '08:00', 'duracion_estimada' => 120, 'tecnicos' => [$tecnico->id]]);

        // 09:00 se cruza con 08:00–10:00.
        $this->postJson('/api/v1/ordenes', $this->datosOrden(['hora' => '09:00', 'tecnicos' => [$tecnico->id]]))
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', 'Andrés Rojas tiene la orden OT-2026-00001 en un horario que se cruza.');

        // 10:00 empieza justo cuando termina la otra.
        $siguiente = $this->crearOrden(['hora' => '10:00', 'tecnicos' => [$tecnico->id]]);
        $this->assertSame('asignada', $siguiente->estado);

        $this->patchJson("/api/v1/ordenes/{$siguiente->id}/reprogramar", ['fecha' => '2026-01-06', 'hora' => '08:30', 'motivo' => 'Cambio de turno'])
            ->assertUnprocessable();
    }

    public function test_el_tecnico_solo_ve_sus_ordenes_y_el_cliente_las_suyas(): void
    {
        $andres = $this->crearTecnico();
        $diana = $this->crearTecnico('Diana Mora');
        $propia = $this->crearOrden(['tecnicos' => [$andres->id]]);
        $ajena = $this->crearOrden(['hora' => '14:00', 'tecnicos' => [$diana->id]]);
        $otroCliente = $this->crearOrden([], $this->crearEquipo());

        $this->actingAs($andres->usuario, 'sanctum')
            ->getJson('/api/v1/ordenes/agenda?desde=2026-01-05&hasta=2026-01-11')
            ->assertOk()
            ->assertJsonCount(1, 'datos')
            ->assertJsonPath('datos.0.numero', $propia->numero);
        $this->getJson("/api/v1/ordenes/{$ajena->id}")->assertForbidden();

        $cliente = $this->usuarioConRol('cliente', ['cliente_id' => $this->equipo->edificio->cliente_id]);
        $this->actingAs($cliente, 'sanctum')
            ->getJson('/api/v1/ordenes')
            ->assertOk()
            ->assertJsonCount(2, 'datos');
        $this->getJson("/api/v1/ordenes/{$otroCliente->id}")->assertForbidden();
        $this->postJson('/api/v1/ordenes', $this->datosOrden())->assertForbidden();
    }

    // ---------------------------------------------------------------- RF-026 y RF-028

    public function test_flujo_de_estados_con_tiempos_y_duracion(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden(['tecnicos' => [$tecnico->id]]);
        $ruta = "/api/v1/ordenes/{$orden->id}/estado";

        // Solo un técnico asignado avanza la orden.
        $this->actingAs($this->coordinador, 'sanctum')->patchJson($ruta, ['estado' => 'en_camino'])->assertForbidden();
        $this->actingAs($this->crearTecnico('Otro')->usuario, 'sanctum')->patchJson($ruta, ['estado' => 'en_camino'])->assertForbidden();

        $this->actingAs($tecnico->usuario, 'sanctum');
        $this->patchJson($ruta, ['estado' => 'finalizada', 'informe' => 'Se cambiaron los amortiguadores.'])
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', 'Una orden Asignada no puede pasar a Finalizada.');

        $this->patchJson($ruta, ['estado' => 'en_camino'])->assertOk()->assertJsonPath('datos.estado', 'en_camino');

        $this->travelTo(CarbonImmutable::parse('2026-01-06 08:05:00'));
        $this->patchJson($ruta, ['estado' => 'en_proceso'])
            ->assertOk()
            ->assertJsonPath('datos.inicio_en', '2026-01-06T08:05:00-05:00');

        $this->patchJson($ruta, ['estado' => 'pendiente'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo'], 'errores');
        $this->patchJson($ruta, ['estado' => 'pendiente', 'motivo' => 'Falta un repuesto'])->assertOk();
        $this->patchJson($ruta, ['estado' => 'en_proceso'])->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-01-06 09:50:30'));
        $this->patchJson($ruta, ['estado' => 'finalizada'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['informe'], 'errores');
        // RF-025: sin las firmas no se finaliza.
        $this->patchJson($ruta, ['estado' => 'finalizada', 'informe' => 'Se cambiaron los amortiguadores del foso.'])
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', 'Para finalizar la orden falta la firma del técnico y la firma de quien recibe.');
        $this->firmar($orden->id);
        $this->patchJson($ruta, ['estado' => 'finalizada', 'informe' => 'Se cambiaron los amortiguadores del foso.'])
            ->assertOk()
            ->assertJsonPath('datos.estado', 'finalizada')
            ->assertJsonPath('datos.duracion_minutos', 106)
            ->assertJsonPath('datos.fin_en', '2026-01-06T09:50:30-05:00');

        // Finalizada es un estado final.
        $this->patchJson($ruta, ['estado' => 'en_proceso'])->assertUnprocessable();
        $this->actingAs($this->coordinador, 'sanctum')
            ->patchJson("/api/v1/ordenes/{$orden->id}/cancelar", ['motivo' => 'Ya no se necesita'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('historial_equipos', ['equipo_id' => $this->equipo->id, 'evento' => 'reparacion']);
    }

    public function test_solo_se_cancela_en_programada_asignada_o_pendiente_con_motivo(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden(['tecnicos' => [$tecnico->id]]);

        $this->patchJson("/api/v1/ordenes/{$orden->id}/cancelar", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo'], 'errores');

        $this->actingAs($tecnico->usuario, 'sanctum')->patchJson("/api/v1/ordenes/{$orden->id}/estado", ['estado' => 'en_camino'])->assertOk();
        $this->actingAs($this->coordinador, 'sanctum')
            ->patchJson("/api/v1/ordenes/{$orden->id}/cancelar", ['motivo' => 'El cliente canceló'])
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', 'Solo se cancelan órdenes programadas, asignadas o pendientes.');

        $otra = $this->crearOrden(['hora' => '14:00', 'tecnicos' => [$tecnico->id]]);
        $this->patchJson("/api/v1/ordenes/{$otra->id}/cancelar", ['motivo' => 'El cliente canceló la visita'])
            ->assertOk()
            ->assertJsonPath('datos.estado', 'cancelada')
            ->assertJsonPath('datos.motivo_cancelacion', 'El cliente canceló la visita');

        Notification::assertSentTo($tecnico->usuario, OrdenTrabajoNotification::class, fn ($n) => $n->evento === 'cancelada');
    }

    // ---------------------------------------------------------------- RF-029

    public function test_la_trazabilidad_registra_cada_cambio_y_no_se_modifica(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden(['tecnicos' => [$tecnico->id]]);

        $respuesta = $this->getJson("/api/v1/ordenes/{$orden->id}")->assertOk();
        $acciones = collect($respuesta->json('datos.trazabilidad'))->pluck('accion')->all();
        $this->assertContains('creada', $acciones);
        $this->assertContains('tecnico_asignado', $acciones);
        $this->assertContains('estado_cambiado', $acciones);

        $registro = TrazabilidadOrden::where('orden_id', $orden->id)->first();
        $this->expectException(LogicException::class);
        $registro->update(['detalle' => 'cambiado']);
    }

    // ---------------------------------------------------------------- Integración con la fase 2

    public function test_la_orden_preventiva_ejecuta_la_visita_del_plan_al_finalizar(): void
    {
        $plan = app(PlanService::class)->crear([
            'nombre' => 'Plan Integral',
            'activo' => true,
            'precios' => [['periodicidad_id' => Periodicidad::where('meses', 1)->value('id'), 'precio' => 100000]],
        ]);
        $this->actingAs($this->administrador, 'sanctum')->postJson('/api/v1/asignaciones', [
            'equipo_id' => $this->equipo->id,
            'plan_id' => $plan->id,
            'periodicidad_id' => Periodicidad::where('meses', 1)->value('id'),
            'fecha_inicio' => '2025-12-06',
            'hora_preferida' => '10:30',
        ])->assertCreated();
        $visita = MantenimientoProgramado::where('equipo_id', $this->equipo->id)->orderBy('fecha_programada')->first();
        $this->assertSame('2026-01-06', $visita->fecha_programada->toDateString());

        $tecnico = $this->crearTecnico();
        $ordenId = $this->actingAs($this->coordinador, 'sanctum')
            ->postJson('/api/v1/ordenes', ['mantenimiento_id' => $visita->id, 'tecnicos' => [$tecnico->id]])
            ->assertCreated()
            ->assertJsonPath('datos.servicio', 'preventivo')
            ->assertJsonPath('datos.fecha', '2026-01-06')
            ->assertJsonPath('datos.hora', '10:30')
            ->json('datos.id');

        // Una visita con orden no se crea dos veces ni se reprograma desde el calendario.
        $this->postJson('/api/v1/ordenes', ['mantenimiento_id' => $visita->id])->assertUnprocessable();
        $this->patchJson("/api/v1/mantenimientos/{$visita->id}/reprogramar", ['fecha' => '2026-01-07', 'motivo' => 'Cambio de fecha'])
            ->assertUnprocessable();

        // Cambiar la periodicidad regenera la programación, pero conserva la visita con orden.
        $asignacion = $visita->asignacion;
        $precio3 = app(PlanService::class)->actualizarPrecios($plan, [['periodicidad_id' => Periodicidad::where('meses', 3)->value('id'), 'precio' => 250000]], 'Precio trimestral');
        $this->assertSame(1, $precio3);
        $this->actingAs($this->administrador, 'sanctum')
            ->patchJson("/api/v1/asignaciones/{$asignacion->id}/plan", ['periodicidad_id' => Periodicidad::where('meses', 3)->value('id'), 'motivo' => 'Visitas trimestrales'])
            ->assertOk();
        $this->assertTrue($visita->fresh()->estaPendiente());

        $this->travelTo(CarbonImmutable::parse('2026-01-06 10:30:00'));
        $this->actingAs($tecnico->usuario, 'sanctum');
        foreach (['en_camino', 'en_proceso'] as $estado) {
            $this->patchJson("/api/v1/ordenes/{$ordenId}/estado", ['estado' => $estado])->assertOk();
        }
        $this->travelTo(CarbonImmutable::parse('2026-01-06 12:00:00'));
        $this->completarPlanilla($ordenId);
        $this->firmar($ordenId);
        $this->patchJson("/api/v1/ordenes/{$ordenId}/estado", ['estado' => 'finalizada', 'informe' => 'Mantenimiento preventivo completo.'])->assertOk();

        $visita->refresh();
        $this->assertSame('ejecutado', $visita->estado);
        $this->assertSame('2026-01-06', $visita->fecha_ejecucion->toDateString());
        $this->assertSame('2026-01-06', $asignacion->fresh()->ultimo_mantenimiento->toDateString());
        // Trimestral desde el 6 de enero: 6 de abril.
        $this->assertSame('2026-04-06', $asignacion->fresh()->proximo_mantenimiento->toDateString());
    }
}
