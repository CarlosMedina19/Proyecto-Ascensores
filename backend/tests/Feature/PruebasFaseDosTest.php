<?php

namespace Tests\Feature;

use App\Models\AsignacionPlan;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Contrato;
use App\Models\Edificio;
use App\Models\EnvioNotificacion;
use App\Models\Equipo;
use App\Models\MantenimientoProgramado;
use App\Models\Periodicidad;
use App\Models\PlanMantenimiento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\AlertaVencimientoContratoNotification;
use App\Notifications\AvisoMantenimientoNotification;
use App\Services\PlanService;
use App\Services\TareasProgramadasService;
use Carbon\CarbonImmutable;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PeriodicidadSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Pruebas funcionales de la fase 2: planes, periodicidad, precios y contratos (RF-010 a RF-021).
 *
 * La fecha actual se fija en el lunes 5 de enero de 2026.
 */
class PruebasFaseDosTest extends TestCase
{
    use RefreshDatabase;

    protected Usuario $administrador;

    protected Usuario $coordinador;

    protected Usuario $contador;

    protected PlanMantenimiento $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-01-05 10:00:00'));
        Notification::fake();
        $this->seed([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class]);

        $this->administrador = $this->usuarioConRol('administrador');
        $this->coordinador = $this->usuarioConRol('coordinador');
        $this->contador = $this->usuarioConRol('contador');

        // Precio por periodicidad: 100.000 por cada mes entre visitas.
        $this->plan = app(PlanService::class)->crear([
            'nombre' => 'Plan Integral',
            'servicios_incluidos' => ['Inspección general', 'Lubricación'],
            'activo' => true,
            'precios' => Periodicidad::all()->map(fn (Periodicidad $p) => [
                'periodicidad_id' => $p->id,
                'precio' => $p->meses * 100000,
            ])->all(),
        ]);
    }

    private function usuarioConRol(string $rol, array $datos = []): Usuario
    {
        return Usuario::factory()->create($datos + [
            'rol_id' => Rol::where('nombre', $rol)->value('id'),
            'activo' => true,
        ]);
    }

    private function periodicidad(int $meses): int
    {
        return Periodicidad::where('meses', $meses)->value('id');
    }

    private function crearEquipo(?Cliente $cliente = null, array $datos = []): Equipo
    {
        $edificio = Edificio::factory()->create(['cliente_id' => ($cliente ?? Cliente::factory()->create())->id]);

        return Equipo::factory()->create($datos + ['edificio_id' => $edificio->id]);
    }

    /** Asigna el plan por la API como administrador y devuelve la asignación. */
    private function asignar(Equipo $equipo, int $meses, string $inicio, array $datos = []): AsignacionPlan
    {
        $respuesta = $this->actingAs($this->administrador, 'sanctum')->postJson('/api/v1/asignaciones', $datos + [
            'equipo_id' => $equipo->id,
            'plan_id' => $this->plan->id,
            'periodicidad_id' => $this->periodicidad($meses),
            'fecha_inicio' => $inicio,
        ]);
        $respuesta->assertCreated();

        return AsignacionPlan::findOrFail($respuesta->json('datos.id'));
    }

    private function pendientes(AsignacionPlan $asignacion)
    {
        return $asignacion->mantenimientos()->pendientes()->orderBy('fecha_teorica')->get();
    }

    // ---------------------------------------------------------------- RF-010 a RF-012

    public function test_crea_plan_con_precio_por_periodicidad(): void
    {
        $this->actingAs($this->administrador, 'sanctum');

        $this->getJson('/api/v1/periodicidades')->assertOk()->assertJsonCount(6, 'datos');

        $respuesta = $this->postJson('/api/v1/planes', [
            'nombre' => 'Plan Básico',
            'servicios_incluidos' => ['Inspección general'],
            'precios' => [
                ['periodicidad_id' => $this->periodicidad(1), 'precio' => 350000],
                ['periodicidad_id' => $this->periodicidad(3), 'precio' => 420000],
            ],
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('datos.nombre', 'Plan Básico')
            ->assertJsonCount(2, 'datos.precios');

        $this->postJson('/api/v1/planes', [
            'nombre' => 'Plan Básico',
            'precios' => [['periodicidad_id' => $this->periodicidad(1), 'precio' => 0]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'precios.0.precio'], 'errores');

        // Después de creado, los precios solo se cambian con motivo.
        $this->patchJson('/api/v1/planes/'.$respuesta->json('datos.id'), [
            'precios' => [['periodicidad_id' => $this->periodicidad(1), 'precio' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['precios'], 'errores');
    }

    public function test_cambio_de_precios_del_catalogo_exige_permiso_y_motivo(): void
    {
        $datos = ['precios' => [['periodicidad_id' => $this->periodicidad(1), 'precio' => 150000]]];

        $this->actingAs($this->coordinador, 'sanctum')
            ->putJson("/api/v1/planes/{$this->plan->id}/precios", $datos + ['motivo' => 'Ajuste anual'])
            ->assertForbidden();

        $this->actingAs($this->contador, 'sanctum')
            ->putJson("/api/v1/planes/{$this->plan->id}/precios", $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo'], 'errores');

        $this->putJson("/api/v1/planes/{$this->plan->id}/precios", $datos + ['motivo' => 'Ajuste anual por IPC'])->assertOk();

        $this->assertSame('150000.00', $this->plan->precioPara($this->periodicidad(1)));
        $this->getJson("/api/v1/planes/{$this->plan->id}/historial-precios")
            ->assertOk()
            ->assertJsonPath('datos.0.precio_anterior', '100000.00')
            ->assertJsonPath('datos.0.precio_nuevo', '150000.00')
            ->assertJsonPath('datos.0.motivo', 'Ajuste anual por IPC');
    }

    // ---------------------------------------------------------------- RF-013, RF-014, RF-017 y RF-018

    public function test_asignar_plan_programa_los_mantenimientos_en_dias_habiles(): void
    {
        $equipo = $this->crearEquipo();
        $asignacion = $this->asignar($equipo, 1, '2025-12-12');

        $this->assertSame('100000.00', $asignacion->precio);
        $this->assertSame('2026-12-12', $asignacion->fecha_fin->toDateString());

        $pendientes = $this->pendientes($asignacion);
        $this->assertCount(12, $pendientes);
        // 12 de enero de 2026 es festivo (Reyes): pasa al martes 13.
        $this->assertSame('2026-01-12', $pendientes[0]->fecha_teorica->toDateString());
        $this->assertSame('2026-01-13', $pendientes[0]->fecha_programada->toDateString());
        // La siguiente se calcula desde la base, no desde la fecha corrida.
        $this->assertSame('2026-02-12', $pendientes[1]->fecha_programada->toDateString());
        $this->assertSame('2026-01-13', $asignacion->proximo_mantenimiento->toDateString());

        $this->getJson("/api/v1/equipos/{$equipo->id}")
            ->assertOk()
            ->assertJsonPath('datos.asignacion_activa.plan.nombre', 'Plan Integral')
            ->assertJsonPath('datos.proximo_mantenimiento', '2026-01-13');

        $this->assertDatabaseHas('historial_equipos', ['equipo_id' => $equipo->id, 'evento' => 'plan_asignado']);
    }

    public function test_un_ascensor_tiene_un_solo_plan_activo(): void
    {
        $equipo = $this->crearEquipo();
        $this->asignar($equipo, 1, '2026-01-05');

        $this->postJson('/api/v1/asignaciones', [
            'equipo_id' => $equipo->id,
            'plan_id' => $this->plan->id,
            'periodicidad_id' => $this->periodicidad(2),
            'fecha_inicio' => '2026-01-05',
        ])->assertUnprocessable()->assertJsonStructure(['mensaje']);

        $inactivo = $this->crearEquipo(null, ['estado' => 'inactivo']);
        $this->postJson('/api/v1/asignaciones', [
            'equipo_id' => $inactivo->id,
            'plan_id' => $this->plan->id,
            'periodicidad_id' => $this->periodicidad(1),
            'fecha_inicio' => '2026-01-05',
        ])->assertUnprocessable();

        // Ni el ascensor ni el plan se eliminan mientras la asignación esté activa.
        $this->deleteJson("/api/v1/equipos/{$equipo->id}")->assertUnprocessable();
        $this->deleteJson("/api/v1/planes/{$this->plan->id}")->assertUnprocessable();
    }

    public function test_solo_contador_o_administrador_fijan_precio_distinto_al_catalogo(): void
    {
        $this->actingAs($this->coordinador, 'sanctum')->postJson('/api/v1/asignaciones', [
            'equipo_id' => $this->crearEquipo()->id,
            'plan_id' => $this->plan->id,
            'periodicidad_id' => $this->periodicidad(1),
            'fecha_inicio' => '2026-01-05',
            'precio' => 90000,
        ])->assertUnprocessable()->assertJsonValidationErrors(['precio'], 'errores');

        $asignacion = $this->asignar($this->crearEquipo(), 1, '2026-01-05', ['precio' => 90000]);
        $this->assertSame('90000.00', $asignacion->precio);
    }

    // ---------------------------------------------------------------- RF-015 y RF-016

    public function test_cambio_de_periodicidad_conserva_los_ejecutados(): void
    {
        $asignacion = $this->asignar($this->crearEquipo(), 1, '2025-12-05');
        $primero = $this->pendientes($asignacion)->first();
        $this->assertSame('2026-01-05', $primero->fecha_programada->toDateString());

        $this->patchJson("/api/v1/mantenimientos/{$primero->id}/ejecutar", ['fecha_ejecucion' => '2026-01-05'])->assertOk();

        $this->patchJson("/api/v1/asignaciones/{$asignacion->id}/plan", ['periodicidad_id' => $this->periodicidad(3)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo'], 'errores');

        $this->patchJson("/api/v1/asignaciones/{$asignacion->id}/plan", [
            'periodicidad_id' => $this->periodicidad(3),
            'motivo' => 'El cliente pidió visitas trimestrales',
        ])->assertOk()
            ->assertJsonPath('datos.periodicidad.meses', 3)
            ->assertJsonPath('datos.precio', '300000.00');

        $pendientes = $this->pendientes($asignacion->fresh());
        // 5 de abril de 2026 es domingo de Pascua: pasa al lunes 6.
        $this->assertSame('2026-04-05', $pendientes[0]->fecha_teorica->toDateString());
        $this->assertSame('2026-04-06', $pendientes[0]->fecha_programada->toDateString());
        $this->assertTrue($pendientes->every(fn ($m) => $m->precio === '300000.00'));

        $primero->refresh();
        $this->assertSame('ejecutado', $primero->estado);
        $this->assertSame('100000.00', $primero->precio);

        $this->assertDatabaseHas('cambios_asignacion', [
            'asignacion_id' => $asignacion->id,
            'campo' => 'periodicidad',
            'valor_anterior' => '1 meses',
            'valor_nuevo' => '3 meses',
        ]);
    }

    public function test_cambio_de_precio_de_la_asignacion_solo_afecta_pendientes(): void
    {
        $asignacion = $this->asignar($this->crearEquipo(), 1, '2025-12-05');
        $primero = $this->pendientes($asignacion)->first();
        $this->patchJson("/api/v1/mantenimientos/{$primero->id}/ejecutar", ['fecha_ejecucion' => '2026-01-05'])->assertOk();

        $this->actingAs($this->coordinador, 'sanctum')
            ->patchJson("/api/v1/asignaciones/{$asignacion->id}/precio", ['precio' => 120000, 'motivo' => 'Ajuste pactado'])
            ->assertForbidden();

        $this->actingAs($this->contador, 'sanctum')
            ->patchJson("/api/v1/asignaciones/{$asignacion->id}/precio", ['precio' => 120000, 'motivo' => 'Ajuste pactado con el cliente'])
            ->assertOk()
            ->assertJsonPath('datos.precio', '120000.00');

        $this->assertTrue($this->pendientes($asignacion)->every(fn ($m) => $m->precio === '120000.00'));
        $this->assertSame('100000.00', $primero->fresh()->precio);
        $this->assertDatabaseHas('historial_precios', [
            'asignacion_id' => $asignacion->id,
            'precio_anterior' => 100000,
            'precio_nuevo' => 120000,
            'motivo' => 'Ajuste pactado con el cliente',
        ]);
    }

    // ---------------------------------------------------------------- RF-017 y RF-018

    public function test_ejecutar_recalcula_desde_la_fecha_real(): void
    {
        $asignacion = $this->asignar($this->crearEquipo(), 1, '2025-12-20');
        $primero = $this->pendientes($asignacion)->first();
        $this->assertSame('2026-01-20', $primero->fecha_teorica->toDateString());

        $this->patchJson("/api/v1/mantenimientos/{$primero->id}/ejecutar", ['fecha_ejecucion' => '2026-01-06'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_ejecucion'], 'errores');

        // Se adelanta: la próxima visita se cuenta desde el 5 de enero.
        $this->patchJson("/api/v1/mantenimientos/{$primero->id}/ejecutar", [
            'fecha_ejecucion' => '2026-01-05',
            'observacion' => 'Visita adelantada por solicitud del cliente',
        ])->assertOk()->assertJsonPath('datos.estado', 'ejecutado');

        $asignacion->refresh();
        $this->assertSame('2026-01-05', $asignacion->ultimo_mantenimiento->toDateString());
        $this->assertSame('2026-02-05', $asignacion->proximo_mantenimiento->toDateString());

        $this->patchJson("/api/v1/mantenimientos/{$primero->id}/ejecutar", ['fecha_ejecucion' => '2026-01-05'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('historial_equipos', ['equipo_id' => $asignacion->equipo_id, 'evento' => 'mantenimiento_preventivo']);
    }

    public function test_ejecutar_una_visita_futura_no_choca_con_la_programacion_nueva(): void
    {
        $asignacion = $this->asignar($this->crearEquipo(), 1, '2025-12-05');
        [$enero, $febrero] = $this->pendientes($asignacion)->take(2)->all();

        // Se ejecuta hoy la visita de febrero: la nueva programación también tiene un 5 de febrero.
        $this->patchJson("/api/v1/mantenimientos/{$febrero->id}/ejecutar", ['fecha_ejecucion' => '2026-01-05'])->assertOk();

        $this->assertSame('cancelado', $enero->fresh()->estado);
        $pendientes = $this->pendientes($asignacion);
        $this->assertSame('2026-02-05', $pendientes->first()->fecha_teorica->toDateString());
        $this->assertSame(1, MantenimientoProgramado::where('asignacion_id', $asignacion->id)->where('estado', 'ejecutado')->count());
    }

    public function test_reprogramar_exige_dia_habil_futuro_y_avisa(): void
    {
        $this->usuarioConRol('cliente', ['cliente_id' => ($cliente = Cliente::factory()->create())->id]);
        $asignacion = $this->asignar($this->crearEquipo($cliente), 1, '2025-12-12');
        $mantenimiento = $this->pendientes($asignacion)->first();

        $this->patchJson("/api/v1/mantenimientos/{$mantenimiento->id}/reprogramar", ['fecha' => '2026-01-08'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo'], 'errores');
        $this->patchJson("/api/v1/mantenimientos/{$mantenimiento->id}/reprogramar", ['fecha' => '2026-01-12', 'motivo' => 'Solicitud del cliente'])
            ->assertUnprocessable();
        $this->patchJson("/api/v1/mantenimientos/{$mantenimiento->id}/reprogramar", ['fecha' => '2026-01-02', 'motivo' => 'Solicitud del cliente'])
            ->assertUnprocessable();

        $this->patchJson("/api/v1/mantenimientos/{$mantenimiento->id}/reprogramar", ['fecha' => '2026-01-08', 'motivo' => 'Solicitud del cliente'])
            ->assertOk()
            ->assertJsonPath('datos.estado', 'reprogramado')
            ->assertJsonPath('datos.fecha_programada', '2026-01-08')
            ->assertJsonPath('datos.fecha_teorica', '2026-01-12');

        $this->assertSame('2026-01-08', $asignacion->fresh()->proximo_mantenimiento->toDateString());
        Notification::assertSentTo($this->coordinador, AvisoMantenimientoNotification::class);
        Notification::assertNotSentTo($this->contador, AvisoMantenimientoNotification::class);
    }

    public function test_calendario_por_rango_y_cliente_solo_ve_lo_suyo(): void
    {
        $cliente = Cliente::factory()->create();
        $usuarioCliente = $this->usuarioConRol('cliente', ['cliente_id' => $cliente->id]);
        $this->asignar($this->crearEquipo($cliente), 1, '2025-12-15');
        $this->asignar($this->crearEquipo(), 1, '2025-12-15');

        $this->getJson('/api/v1/mantenimientos?desde=2026-01-01&hasta=2026-03-31')
            ->assertOk()
            ->assertJsonCount(6, 'datos');

        $this->getJson('/api/v1/mantenimientos?desde=2026-01-01&hasta=2026-12-31')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hasta'], 'errores');

        $this->actingAs($usuarioCliente, 'sanctum')
            ->getJson('/api/v1/mantenimientos?desde=2026-01-01&hasta=2026-03-31')
            ->assertOk()
            ->assertJsonCount(3, 'datos');

        $ajeno = MantenimientoProgramado::whereHas('equipo.edificio', fn ($c) => $c->where('cliente_id', '!=', $cliente->id))->first();
        $this->getJson("/api/v1/mantenimientos/{$ajeno->id}")->assertForbidden();
        $this->patchJson("/api/v1/mantenimientos/{$ajeno->id}/cancelar", ['motivo' => 'No lo necesito'])->assertForbidden();
    }

    // ---------------------------------------------------------------- RF-019 y RF-020

    public function test_contrato_valida_equipos_del_cliente_y_traslapes(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $cliente = Cliente::factory()->create();
        $equipo = $this->crearEquipo($cliente);
        $ajeno = $this->crearEquipo();

        $datos = [
            'cliente_id' => $cliente->id,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'valor' => 12000000,
            'condiciones' => 'Pago mensual',
        ];

        $this->postJson('/api/v1/contratos', $datos + ['equipos' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['equipos'], 'errores');

        $this->postJson('/api/v1/contratos', $datos + ['equipos' => [$ajeno->id]])->assertUnprocessable();

        $this->postJson('/api/v1/contratos', $datos + ['equipos' => [$equipo->id]])
            ->assertCreated()
            ->assertJsonPath('datos.numero', 'CT-2026-0001')
            ->assertJsonPath('datos.estado', 'vigente');

        // El mismo ascensor no puede estar en dos contratos vigentes en fechas que se cruzan.
        $this->postJson('/api/v1/contratos', ['fecha_inicio' => '2026-06-01', 'fecha_fin' => '2027-05-31'] + $datos + ['equipos' => [$equipo->id]])
            ->assertUnprocessable();
    }

    public function test_renovacion_crea_contrato_nuevo_y_extiende_los_planes(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $cliente = Cliente::factory()->create();
        $equipo = $this->crearEquipo($cliente);

        $contratoId = $this->postJson('/api/v1/contratos', [
            'cliente_id' => $cliente->id,
            'fecha_inicio' => '2025-03-01',
            'fecha_fin' => '2026-02-28',
            'valor' => 12000000,
            'equipos' => [$equipo->id],
        ])->assertCreated()->json('datos.id');

        $asignacion = $this->asignar($equipo, 1, '2025-12-05', ['contrato_id' => $contratoId]);
        $this->assertSame('2026-02-28', $asignacion->fecha_fin->toDateString());
        $this->assertCount(2, $this->pendientes($asignacion));

        $this->postJson("/api/v1/contratos/{$contratoId}/seguimientos", ['resultado' => 'contactado', 'observacion' => 'Llamada a la administradora'])
            ->assertCreated()
            ->assertJsonPath('datos.resultado', 'contactado');
        $this->postJson("/api/v1/contratos/{$contratoId}/seguimientos", ['resultado' => 'renovado'])->assertUnprocessable();

        $this->postJson("/api/v1/contratos/{$contratoId}/renovar", ['fecha_fin' => '2027-02-28', 'valor' => 13000000])
            ->assertCreated()
            ->assertJsonPath('datos.numero', 'CT-2026-0002')
            ->assertJsonPath('datos.contrato_anterior.id', $contratoId)
            ->assertJsonPath('datos.fecha_inicio', '2026-03-01');

        $anterior = Contrato::findOrFail($contratoId);
        $this->assertSame('renovado', $anterior->estado);
        $this->assertSame(3, $anterior->seguimientos()->count() + 1);

        $asignacion->refresh();
        $this->assertSame('2027-02-28', $asignacion->fecha_fin->toDateString());
        $this->assertNotSame($contratoId, $asignacion->contrato_id);
        $this->assertCount(14, $this->pendientes($asignacion));
    }

    public function test_cliente_solo_consulta_sus_contratos(): void
    {
        $propio = Cliente::factory()->create();
        $usuarioCliente = $this->usuarioConRol('cliente', ['cliente_id' => $propio->id]);

        foreach ([$propio, Cliente::factory()->create()] as $cliente) {
            $this->actingAs($this->administrador, 'sanctum')->postJson('/api/v1/contratos', [
                'cliente_id' => $cliente->id,
                'fecha_inicio' => '2026-01-01',
                'fecha_fin' => '2026-12-31',
                'valor' => 1000000,
                'equipos' => [$this->crearEquipo($cliente)->id],
            ])->assertCreated();
        }

        $this->actingAs($usuarioCliente, 'sanctum')
            ->getJson('/api/v1/contratos')
            ->assertOk()
            ->assertJsonCount(1, 'datos')
            ->assertJsonPath('datos.0.cliente_id', $propio->id);

        $ajeno = Contrato::where('cliente_id', '!=', $propio->id)->firstOrFail();
        $this->getJson("/api/v1/contratos/{$ajeno->id}")->assertForbidden();
        $this->postJson('/api/v1/contratos', [
            'cliente_id' => $propio->id,
            'fecha_inicio' => '2027-01-01',
            'fecha_fin' => '2027-12-31',
            'valor' => 1000000,
            'equipos' => [$this->crearEquipo($propio)->id],
        ])->assertForbidden();
    }

    // ---------------------------------------------------------------- RF-020 y RF-021 (tareas programadas)

    public function test_alerta_de_vencimiento_se_envia_una_sola_vez(): void
    {
        $cliente = Cliente::factory()->create();
        $this->actingAs($this->administrador, 'sanctum')->postJson('/api/v1/contratos', [
            'cliente_id' => $cliente->id,
            'fecha_inicio' => '2025-02-01',
            'fecha_fin' => '2026-01-25',
            'valor' => 1000000,
            'equipos' => [$this->crearEquipo($cliente)->id],
        ])->assertCreated();

        $tareas = app(TareasProgramadasService::class);
        // Administrador, coordinador y contador, por base de datos y correo.
        $this->assertSame(6, $tareas->alertarVencimientoContratos());
        $this->assertSame(0, $tareas->alertarVencimientoContratos());

        Notification::assertSentToTimes($this->contador, AlertaVencimientoContratoNotification::class, 2);
        $this->assertSame(6, EnvioNotificacion::where('estado', 'enviado')->count());
    }

    public function test_avisos_de_mantenimiento_no_se_repiten(): void
    {
        Configuracion::updateOrCreate(['clave' => 'notificaciones.canales'], ['valor' => ['database']]);
        $asignacion = $this->asignar($this->crearEquipo(), 1, '2025-12-12');
        // El 13 de enero (próxima visita) está a 8 días; se mueve a 7 para recibir el aviso.
        $this->pendientes($asignacion)->first()->update(['fecha_programada' => '2026-01-12']);

        $tareas = app(TareasProgramadasService::class);
        $this->assertSame(1, $tareas->enviarAvisosMantenimiento());
        $this->assertSame(0, $tareas->enviarAvisosMantenimiento());

        Notification::assertSentToTimes($this->coordinador, AvisoMantenimientoNotification::class, 1);
        $this->assertDatabaseHas('envios_notificacion', ['usuario_id' => $this->coordinador->id, 'tipo' => 'aviso_7d_20260112', 'estado' => 'enviado']);
    }

    public function test_procesar_vigencias_finaliza_planes_y_vence_contratos(): void
    {
        $cliente = Cliente::factory()->create();
        $equipo = $this->crearEquipo($cliente);
        $contratoId = $this->actingAs($this->administrador, 'sanctum')->postJson('/api/v1/contratos', [
            'cliente_id' => $cliente->id,
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => '2026-03-31',
            'valor' => 1000000,
            'equipos' => [$equipo->id],
        ])->json('datos.id');
        $conPlan = $this->asignar($equipo, 1, '2025-12-15', ['contrato_id' => $contratoId]);
        $vencido = $this->asignar($this->crearEquipo(), 1, '2025-10-01', ['fecha_fin' => '2026-02-01']);

        $this->travelTo(CarbonImmutable::parse('2026-04-02 00:15:00'));
        $resultado = app(TareasProgramadasService::class)->procesarVigencias();

        $this->assertSame(['asignaciones_finalizadas' => 2, 'contratos_vencidos' => 1], $resultado);
        $this->assertSame('finalizada', $vencido->fresh()->estado);
        $this->assertSame('vencido', Contrato::find($contratoId)->estado);
        $this->assertCount(0, $this->pendientes($conPlan));
        $this->assertNull($vencido->fresh()->proximo_mantenimiento);
    }

    // ---------------------------------------------------------------- Configuración

    public function test_solo_el_administrador_cambia_la_configuracion(): void
    {
        $this->actingAs($this->coordinador, 'sanctum')
            ->putJson('/api/v1/configuracion', ['notificaciones_dias_aviso' => [10, 3]])
            ->assertForbidden();

        $this->actingAs($this->administrador, 'sanctum')
            ->putJson('/api/v1/configuracion', ['notificaciones_canales' => ['sms']])
            ->assertUnprocessable();

        $this->putJson('/api/v1/configuracion', ['notificaciones_dias_aviso' => [10, 3], 'contratos_dias_alerta_vencimiento' => 45])
            ->assertOk()
            ->assertJsonPath('mensaje', 'Configuración actualizada correctamente');

        $this->assertSame([10, 3], Configuracion::valor('notificaciones.dias_aviso'));
        $this->assertSame(45, Configuracion::valor('contratos.dias_alerta_vencimiento'));
        $this->assertDatabaseHas('registros_auditoria', ['modulo' => 'configuracion']);
    }
}
