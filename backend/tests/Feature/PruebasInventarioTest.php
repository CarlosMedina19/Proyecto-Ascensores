<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\EnvioNotificacion;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\MovimientoInventario;
use App\Models\OrdenTrabajo;
use App\Models\Repuesto;
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
use LogicException;
use Tests\TestCase;

/**
 * RF-040 a RF-043: repuestos, movimientos, consumo automático al finalizar la orden y alertas de
 * stock mínimo. La fecha se fija en el lunes 5 de enero de 2026 a las 10:00.
 */
class PruebasInventarioTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private Usuario $coordinador;

    private Tecnico $tecnico;

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
        $edificio = Edificio::factory()->create(['cliente_id' => Cliente::factory()->create()->id]);
        $this->equipo = Equipo::factory()->create(['edificio_id' => $edificio->id]);
    }

    private function usuario(string $rol, array $datos = []): Usuario
    {
        return Usuario::factory()->create($datos + ['rol_id' => Rol::where('nombre', $rol)->value('id'), 'activo' => true]);
    }

    private function repuesto(array $datos = []): int
    {
        return $this->actingAs($this->coordinador, 'sanctum')->postJson('/api/v1/repuestos', $datos + [
            'codigo' => 'ZAP-001', 'nombre' => 'Zapata de cabina', 'unidad' => 'unidad', 'precio' => 45000, 'stock' => 10, 'stock_minimo' => 3,
        ])->assertCreated()->json('datos.id');
    }

    /** Orden correctiva asignada al técnico y en proceso. */
    private function ordenEnProceso(): OrdenTrabajo
    {
        $id = $this->actingAs($this->coordinador, 'sanctum')->postJson('/api/v1/ordenes', [
            'servicio' => 'correctivo', 'cliente_id' => $this->equipo->edificio->cliente_id, 'edificio_id' => $this->equipo->edificio_id,
            'equipo_id' => $this->equipo->id, 'fecha' => '2026-01-05', 'hora' => '10:00', 'tecnicos' => [$this->tecnico->id],
        ])->assertCreated()->json('datos.id');
        $this->actingAs($this->tecnico->usuario, 'sanctum');
        foreach (['en_camino', 'en_proceso'] as $estado) {
            $this->patchJson("/api/v1/ordenes/{$id}/estado", ['estado' => $estado])->assertOk();
        }

        return OrdenTrabajo::findOrFail($id);
    }

    private function finalizar(OrdenTrabajo $orden): void
    {
        $this->actingAs($this->tecnico->usuario, 'sanctum');
        foreach (['tecnico' => 'Andrés Rojas', 'cliente' => 'Laura Gómez'] as $tipo => $nombre) {
            $this->postJson("/api/v1/ordenes/{$orden->id}/firmas", ['tipo' => $tipo, 'nombre' => $nombre, 'imagen' => 'data:image/png;base64,'.self::PNG])->assertCreated();
        }
        $this->patchJson("/api/v1/ordenes/{$orden->id}/estado", ['estado' => 'finalizada', 'informe' => 'Se cambiaron las zapatas de la cabina.'])->assertOk();
    }

    public function test_el_repuesto_se_registra_con_su_stock_inicial_como_entrada(): void
    {
        $id = $this->repuesto();
        $this->getJson("/api/v1/repuestos/{$id}")->assertOk()->assertJsonPath('datos.stock', 10)->assertJsonPath('datos.bajo_minimo', false);
        $this->getJson("/api/v1/repuestos/{$id}/movimientos")->assertOk()
            ->assertJsonPath('datos.0.tipo', 'entrada')->assertJsonPath('datos.0.motivo', 'Inventario inicial.');

        // Código único, valores no negativos y el stock no se edita directamente.
        $this->postJson('/api/v1/repuestos', ['codigo' => 'ZAP-001', 'nombre' => 'Otra', 'unidad' => 'unidad', 'precio' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors(['codigo', 'precio'], 'errores');
        $this->putJson("/api/v1/repuestos/{$id}", ['stock' => 99])->assertUnprocessable()
            ->assertJsonPath('errores.stock.0', 'El stock no se edita: registre una entrada, una salida o un ajuste.');
    }

    public function test_la_salida_no_supera_el_stock_y_el_ajuste_lleva_al_valor_contado(): void
    {
        $id = $this->repuesto();
        $this->postJson("/api/v1/repuestos/{$id}/movimientos", ['tipo' => 'salida', 'cantidad' => 12, 'motivo' => 'Préstamo a otra sede'])
            ->assertUnprocessable();
        $this->postJson("/api/v1/repuestos/{$id}/movimientos", ['tipo' => 'salida', 'cantidad' => 4, 'motivo' => 'Préstamo a otra sede'])
            ->assertCreated()->assertJsonPath('repuesto.stock', 6);
        $this->postJson("/api/v1/repuestos/{$id}/movimientos", ['tipo' => 'ajuste', 'stock_contado' => 8, 'motivo' => 'Conteo físico del mes'])
            ->assertCreated()->assertJsonPath('datos.variacion', 2)->assertJsonPath('repuesto.stock', 8);

        // Los movimientos no se editan ni se eliminan.
        $this->expectException(LogicException::class);
        MovimientoInventario::firstOrFail()->update(['cantidad' => 1]);
    }

    public function test_el_stock_minimo_alerta_una_sola_vez_hasta_que_se_repone(): void
    {
        $id = $this->repuesto();
        $alertas = fn () => EnvioNotificacion::where('tipo', 'like', 'stock_minimo_%')->distinct('tipo')->count('tipo');

        $this->postJson("/api/v1/repuestos/{$id}/movimientos", ['tipo' => 'salida', 'cantidad' => 7, 'motivo' => 'Uso en obra'])->assertCreated();
        $this->assertSame(1, $alertas());
        $this->postJson("/api/v1/repuestos/{$id}/movimientos", ['tipo' => 'salida', 'cantidad' => 1, 'motivo' => 'Uso en obra'])->assertCreated();
        $this->assertSame(1, $alertas(), 'No se repite mientras siga bajo el mínimo.');
        $this->getJson('/api/v1/repuestos?bajo_minimo=1')->assertJsonCount(1, 'datos')->assertJsonPath('resumen.bajo_minimo', 1);

        $this->postJson("/api/v1/repuestos/{$id}/movimientos", ['tipo' => 'entrada', 'cantidad' => 10, 'motivo' => 'Compra'])->assertCreated();
        $this->assertNull(Repuesto::find($id)->alerta_stock_en);
        $this->postJson("/api/v1/repuestos/{$id}/movimientos", ['tipo' => 'salida', 'cantidad' => 10, 'motivo' => 'Uso en obra'])->assertCreated();
        $this->assertSame(2, $alertas(), 'Tras reponer, una nueva baja vuelve a alertar.');
    }

    public function test_los_repuestos_de_la_orden_se_descuentan_al_finalizar_y_el_faltante_queda_registrado(): void
    {
        $zapata = $this->repuesto();
        $aceite = $this->repuesto(['codigo' => 'ACE-002', 'nombre' => 'Aceite hidráulico', 'unidad' => 'litro', 'stock' => 2, 'stock_minimo' => 0]);
        $orden = $this->ordenEnProceso();

        $this->actingAs($this->tecnico->usuario, 'sanctum');
        $this->postJson("/api/v1/ordenes/{$orden->id}/repuestos", ['repuesto_id' => $zapata, 'cantidad' => 4])->assertCreated();
        $this->postJson("/api/v1/ordenes/{$orden->id}/repuestos", ['repuesto_id' => $aceite, 'cantidad' => 5])->assertCreated();
        // Antes de finalizar no se descuenta nada.
        $this->assertSame('10.00', Repuesto::find($zapata)->stock);

        $this->finalizar($orden);

        $this->assertSame('6.00', Repuesto::find($zapata)->stock);
        $this->assertSame('0.00', Repuesto::find($aceite)->stock, 'El stock no queda negativo.');
        $this->getJson("/api/v1/ordenes/{$orden->id}/repuestos")->assertOk()
            ->assertJsonPath('datos.1.cantidad_descontada', 2)->assertJsonPath('datos.1.faltante', 3);
        $this->assertTrue(EnvioNotificacion::where('tipo', 'like', 'inventario_faltante_%')->exists());
        $this->assertDatabaseHas('historial_equipos', ['equipo_id' => $this->equipo->id, 'evento' => 'repuestos']);
        $this->assertSame(1, MovimientoInventario::where('orden_id', $orden->id)->where('repuesto_id', $zapata)->where('tipo', 'consumo')->count());

        // Una orden finalizada ya no cambia sus repuestos.
        $this->postJson("/api/v1/ordenes/{$orden->id}/repuestos", ['repuesto_id' => $zapata, 'cantidad' => 1])->assertUnprocessable();
    }

    public function test_la_orden_cancelada_no_descuenta_y_los_permisos_se_respetan(): void
    {
        $zapata = $this->repuesto();
        $orden = $this->ordenEnProceso();
        $this->actingAs($this->tecnico->usuario, 'sanctum')->postJson("/api/v1/ordenes/{$orden->id}/repuestos", ['repuesto_id' => $zapata, 'cantidad' => 2])->assertCreated();
        $this->patchJson("/api/v1/ordenes/{$orden->id}/estado", ['estado' => 'pendiente', 'motivo' => 'Falta el repuesto'])->assertOk();
        $this->actingAs($this->coordinador, 'sanctum')->patchJson("/api/v1/ordenes/{$orden->id}/cancelar", ['motivo' => 'El cliente aplazó el trabajo'])->assertOk();
        $this->assertSame('10.00', Repuesto::find($zapata)->stock);

        // El técnico consulta pero no registra movimientos; el cliente no ve el inventario.
        $this->actingAs($this->tecnico->usuario, 'sanctum')->getJson('/api/v1/repuestos')->assertOk();
        $this->postJson("/api/v1/repuestos/{$zapata}/movimientos", ['tipo' => 'entrada', 'cantidad' => 1, 'motivo' => 'Compra'])->assertForbidden();
        $this->actingAs($this->usuario('cliente', ['cliente_id' => $this->equipo->edificio->cliente_id]), 'sanctum')->getJson('/api/v1/repuestos')->assertForbidden();
        $this->actingAs($this->usuario('supervisor'), 'sanctum')->getJson('/api/v1/repuestos')->assertOk();
    }

    public function test_un_repuesto_con_movimientos_no_se_elimina_se_desactiva(): void
    {
        $id = $this->repuesto();
        $this->deleteJson("/api/v1/repuestos/{$id}")->assertUnprocessable();
        $this->patchJson("/api/v1/repuestos/{$id}/alternar-estado")->assertOk()->assertJsonPath('datos.activo', false);

        $sinMovimientos = $this->repuesto(['codigo' => 'BOT-003', 'nombre' => 'Botón de cabina', 'stock' => 0]);
        $this->deleteJson("/api/v1/repuestos/{$sinMovimientos}")->assertOk();
        $this->assertDatabaseMissing('repuestos', ['id' => $sinMovimientos]);
    }
}
