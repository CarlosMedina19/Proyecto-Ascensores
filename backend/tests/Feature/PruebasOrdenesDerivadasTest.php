<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Emergencia;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\OrdenTrabajo;
use App\Models\PlanillaResultado;
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
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Órdenes de trabajo que nacen de una emergencia o de una cotización aprobada, cotización desde un
 * hallazgo de la planilla e indicadores en PDF. La fecha se fija en el lunes 5 de enero de 2026 a las 10:00.
 */
class PruebasOrdenesDerivadasTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $coordinador;

    private Tecnico $tecnico;

    private Cliente $cliente;

    private Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-01-05 10:00:00'));
        Notification::fake();
        Bus::fake();
        Storage::fake('local');
        $this->seed([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class, EspecialidadSeeder::class, ChecklistSeeder::class]);

        $this->coordinador = $this->usuario('coordinador');
        $usuario = $this->usuario('tecnico', ['nombre' => 'Andrés Rojas']);
        $this->tecnico = Tecnico::create(['usuario_id' => $usuario->id, 'tipo_documento' => 'CC', 'numero_documento' => '1110450002', 'disponibilidad' => 'disponible']);
        $this->tecnico->especialidades()->attach(Especialidad::first());
        $this->cliente = Cliente::factory()->create();
        $edificio = Edificio::factory()->create(['cliente_id' => $this->cliente->id]);
        $this->equipo = Equipo::factory()->create(['edificio_id' => $edificio->id]);
    }

    private function usuario(string $rol, array $datos = []): Usuario
    {
        return Usuario::factory()->create($datos + ['rol_id' => Rol::where('nombre', $rol)->value('id'), 'activo' => true]);
    }

    /** Emergencia de ayer, atendida en 40 minutos (resuelta por defecto). */
    private function emergencia(string $estado = 'resuelta'): Emergencia
    {
        static $n = 0;
        $n++;

        return Emergencia::create([
            'numero' => sprintf('EM-2026-%05d', 900 + $n), 'equipo_id' => $this->equipo->id, 'tipo' => 'falla', 'origen' => 'panel',
            'personas_atrapadas' => false, 'estado' => $estado, 'minutos_objetivo' => 60,
            'reportada_en' => now()->subDay(), 'llegada_en' => now()->subDay()->addMinutes(40),
            'resuelta_en' => now()->subDay()->addHours(2), 'solucion' => 'Se cambió el contactor.',
        ]);
    }

    private function cotizacion(array $datos = []): int
    {
        return $this->actingAs($this->coordinador, 'sanctum')->postJson('/api/v1/cotizaciones', $datos + [
            'equipo_id' => $this->equipo->id,
            'titulo' => 'Cambio de amortiguadores del foso',
            'items' => [
                ['descripcion' => 'Amortiguador hidráulico', 'cantidad' => 2, 'valor_unitario' => 850000],
                ['descripcion' => 'Mano de obra', 'cantidad' => 1, 'valor_unitario' => 300000],
            ],
        ])->assertCreated()->json('datos.id');
    }

    public function test_la_emergencia_abierta_genera_una_orden_critica_para_hoy_una_sola_vez(): void
    {
        $id = $this->actingAs($this->coordinador, 'sanctum')
            ->postJson('/api/v1/emergencias', ['equipo_id' => $this->equipo->id, 'tipo' => 'falla', 'descripcion' => 'Se detiene entre pisos.'])
            ->assertCreated()->json('datos.id');

        $respuesta = $this->postJson("/api/v1/emergencias/{$id}/orden")
            ->assertCreated()
            ->assertJsonPath('datos.servicio', 'correctivo')
            ->assertJsonPath('datos.prioridad', 'critica')
            ->assertJsonPath('datos.fecha', '2026-01-05');
        $this->assertStringContainsString('Se detiene entre pisos.', $respuesta->json('datos.descripcion'));

        $numero = $respuesta->json('datos.numero');
        $this->getJson("/api/v1/emergencias/{$id}")->assertOk()->assertJsonPath('datos.orden.numero', $numero);
        $this->postJson("/api/v1/emergencias/{$id}/orden")
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', "La emergencia ya tiene la orden {$numero}.");

        // La orden es la misma falla: el indicador no la cuenta dos veces.
        $this->assertSame(1, $this->getJson('/api/v1/indicadores/servicio')->json('datos.mtbf.fallas'));
    }

    public function test_la_emergencia_resuelta_va_al_siguiente_dia_habil_y_la_cancelada_no_genera_orden(): void
    {
        $resuelta = $this->emergencia();
        $this->assertFalse($resuelta->abierta());
        $this->actingAs($this->coordinador, 'sanctum')->postJson("/api/v1/emergencias/{$resuelta->id}/orden")
            ->assertCreated()->assertJsonPath('datos.fecha', '2026-01-06')->assertJsonPath('datos.prioridad', 'alta');

        $cancelada = $this->emergencia('cancelada');
        $this->postJson("/api/v1/emergencias/{$cancelada->id}/orden")->assertUnprocessable();

        // El técnico no crea órdenes.
        $otra = $this->emergencia();
        $this->actingAs($this->tecnico->usuario, 'sanctum')->postJson("/api/v1/emergencias/{$otra->id}/orden")->assertForbidden();
    }

    public function test_solo_la_cotizacion_aprobada_genera_su_orden(): void
    {
        $id = $this->cotizacion();
        $this->postJson("/api/v1/cotizaciones/{$id}/orden")
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', 'Solo una cotización aprobada por el cliente genera la orden de trabajo.');

        $cliente = $this->usuario('cliente', ['cliente_id' => $this->cliente->id]);
        $this->actingAs($cliente, 'sanctum')->postJson("/api/v1/cotizaciones/{$id}/respuesta", ['decision' => 'aprobada'])->assertOk();
        $this->actingAs($cliente, 'sanctum')->postJson("/api/v1/cotizaciones/{$id}/orden")->assertForbidden();

        $respuesta = $this->actingAs($this->coordinador, 'sanctum')->postJson("/api/v1/cotizaciones/{$id}/orden", ['tecnicos' => [$this->tecnico->id]])
            ->assertCreated()
            ->assertJsonPath('datos.servicio', 'correctivo')
            ->assertJsonPath('datos.estado', 'asignada')
            ->assertJsonPath('datos.fecha', '2026-01-06');
        $this->assertStringContainsString('2 × Amortiguador hidráulico', $respuesta->json('datos.descripcion'));

        $this->getJson("/api/v1/cotizaciones/{$id}")->assertJsonPath('datos.orden.numero', $respuesta->json('datos.numero'));
        $this->postJson("/api/v1/cotizaciones/{$id}/orden")->assertUnprocessable();
        $this->assertSame(1, OrdenTrabajo::where('servicio', 'correctivo')->count());
    }

    public function test_la_cotizacion_desde_un_hallazgo_exige_un_item_regular_o_malo_del_mismo_ascensor(): void
    {
        $orden = OrdenTrabajo::findOrFail($this->actingAs($this->coordinador, 'sanctum')->postJson('/api/v1/ordenes', [
            'servicio' => 'preventivo', 'cliente_id' => $this->cliente->id, 'edificio_id' => $this->equipo->edificio_id,
            'equipo_id' => $this->equipo->id, 'fecha' => '2026-01-05', 'hora' => '10:00', 'tecnicos' => [$this->tecnico->id],
        ])->assertCreated()->json('datos.id'));
        $this->actingAs($this->tecnico->usuario, 'sanctum');
        foreach (['en_camino', 'en_proceso'] as $estado) {
            $this->patchJson("/api/v1/ordenes/{$orden->id}/estado", ['estado' => $estado])->assertOk();
        }
        $item = fn (string $codigo) => ChecklistItem::where('codigo', $codigo)->value('id');
        $this->putJson("/api/v1/ordenes/{$orden->id}/planilla", ['resultados' => [
            ['item_id' => $item('FO-04'), 'resultado' => 'INS', 'estado' => 'M', 'observacion' => 'Amortiguador con fuga de aceite'],
            ['item_id' => $item('FO-01'), 'resultado' => 'INS', 'estado' => 'B'],
        ]])->assertOk();
        $malo = PlanillaResultado::where('item_id', $item('FO-04'))->value('id');
        $bueno = PlanillaResultado::where('item_id', $item('FO-01'))->value('id');

        $id = $this->cotizacion(['resultado_origen_id' => $malo]);
        $this->getJson("/api/v1/cotizaciones/{$id}")->assertJsonPath('datos.resultado_origen_id', $malo);

        $this->actingAs($this->coordinador, 'sanctum')->postJson('/api/v1/cotizaciones', [
            'equipo_id' => $this->equipo->id, 'resultado_origen_id' => $bueno, 'titulo' => 'x',
            'items' => [['descripcion' => 'x', 'cantidad' => 1, 'valor_unitario' => 1]],
        ])->assertUnprocessable()->assertJsonPath('mensaje', 'Solo se cotizan los ítems en estado Regular o Malo.');

        $otroEquipo = Equipo::factory()->create(['edificio_id' => $this->equipo->edificio_id]);
        $this->postJson('/api/v1/cotizaciones', [
            'equipo_id' => $otroEquipo->id, 'resultado_origen_id' => $malo, 'titulo' => 'x',
            'items' => [['descripcion' => 'x', 'cantidad' => 1, 'valor_unitario' => 1]],
        ])->assertUnprocessable()->assertJsonPath('mensaje', 'El hallazgo no corresponde a este ascensor.');
    }

    public function test_la_vigencia_vencida_se_explica_en_espanol(): void
    {
        $this->actingAs($this->coordinador, 'sanctum')->postJson('/api/v1/cotizaciones', [
            'equipo_id' => $this->equipo->id, 'titulo' => 'x', 'vigente_hasta' => '2026-01-01',
            'items' => [['descripcion' => 'x', 'cantidad' => 1, 'valor_unitario' => 1]],
        ])->assertUnprocessable()->assertJsonPath('errores.vigente_hasta.0', 'El campo vigencia debe ser una fecha posterior o igual a hoy.');
    }

    public function test_los_indicadores_se_descargan_en_pdf_y_el_cliente_solo_ve_los_suyos(): void
    {
        $this->emergencia();

        $respuesta = $this->actingAs($this->coordinador, 'sanctum')->get('/api/v1/indicadores/servicio/pdf?desde=2025-12-01&hasta=2026-01-05');
        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
        $this->assertStringContainsString('SIGMA_Indicadores_2025-12-01_2026-01-05.pdf', $respuesta->headers->get('Content-Disposition'));

        $cliente = $this->usuario('cliente', ['cliente_id' => $this->cliente->id]);
        $this->actingAs($cliente, 'sanctum')->get('/api/v1/indicadores/servicio/pdf')->assertOk();
        $ajeno = $this->usuario('cliente', ['cliente_id' => Cliente::factory()->create()->id]);
        $this->actingAs($ajeno, 'sanctum')->getJson("/api/v1/indicadores/servicio/pdf?equipo_id={$this->equipo->id}")->assertForbidden();
    }

    public function test_el_descuento_se_resta_antes_del_iva_y_no_supera_los_renglones(): void
    {
        $this->actingAs($this->coordinador, 'sanctum')->postJson('/api/v1/cotizaciones', [
            'equipo_id' => $this->equipo->id, 'titulo' => 'Cambio de amortiguadores', 'iva_porcentaje' => 19, 'descuento' => 200000,
            'items' => [['descripcion' => 'Amortiguador hidráulico', 'cantidad' => 2, 'valor_unitario' => 850000], ['descripcion' => 'Mano de obra', 'cantidad' => 1, 'valor_unitario' => 300000]],
        ])->assertCreated()
            ->assertJsonPath('datos.subtotal', 2000000)
            ->assertJsonPath('datos.descuento', 200000)
            ->assertJsonPath('datos.iva', 342000)
            ->assertJsonPath('datos.total', 2142000);

        $this->postJson('/api/v1/cotizaciones', [
            'equipo_id' => $this->equipo->id, 'titulo' => 'x', 'descuento' => 5000,
            'items' => [['descripcion' => 'x', 'cantidad' => 1, 'valor_unitario' => 1000]],
        ])->assertUnprocessable()->assertJsonPath('mensaje', 'El descuento no puede ser mayor que el valor de los renglones.');
    }

    public function test_la_cotizacion_aprobada_se_factura_una_sola_vez_por_su_total(): void
    {
        $id = $this->cotizacion();
        $contador = $this->usuario('contador');
        $this->actingAs($contador, 'sanctum')->postJson("/api/v1/cotizaciones/{$id}/factura", ['numero' => 'FV-1001'])
            ->assertUnprocessable()->assertJsonPath('mensaje', 'Solo una cotización aprobada por el cliente se puede facturar.');

        $this->actingAs($this->usuario('cliente', ['cliente_id' => $this->cliente->id]), 'sanctum')
            ->postJson("/api/v1/cotizaciones/{$id}/respuesta", ['decision' => 'aprobada'])->assertOk();
        // El técnico no factura.
        $this->actingAs($this->tecnico->usuario, 'sanctum')->postJson("/api/v1/cotizaciones/{$id}/factura", ['numero' => 'FV-1001'])->assertForbidden();

        $this->actingAs($contador, 'sanctum')->postJson("/api/v1/cotizaciones/{$id}/factura", ['numero' => 'FV-1001'])
            ->assertCreated()->assertJsonPath('datos.factura.numero', 'FV-1001');
        $this->assertDatabaseHas('facturas', ['numero' => 'FV-1001', 'cliente_id' => $this->cliente->id, 'valor' => 2380000, 'saldo' => 2380000, 'fecha_vencimiento' => '2026-02-04']);
        $this->postJson("/api/v1/cotizaciones/{$id}/factura", ['numero' => 'FV-1002'])->assertUnprocessable();
    }
}
