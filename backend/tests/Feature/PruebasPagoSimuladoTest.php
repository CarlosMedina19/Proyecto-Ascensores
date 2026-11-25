<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Pasarela de prueba del panel: el cliente ve el proceso completo de pago sin llaves de Wompi. */
class PruebasPagoSimuladoTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $usuarioCliente;

    private Factura $factura;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00:00'));
        Notification::fake();
        Http::preventStrayRequests();
        $this->seed([RolSeeder::class, PermisoSeeder::class, ConfiguracionSeeder::class]);
        config(['services.wompi.llave_publica' => null, 'services.wompi.secreto_integridad' => null, 'services.wompi.simulado' => true]);
        $cliente = Cliente::factory()->create();
        $this->usuarioCliente = Usuario::factory()->create(['rol_id' => Rol::where('nombre', 'cliente')->value('id'), 'activo' => true, 'cliente_id' => $cliente->id]);
        $this->factura = Factura::create([
            'cliente_id' => $cliente->id, 'numero' => 'FV-950', 'fecha_emision' => '2026-11-01', 'fecha_vencimiento' => '2026-11-30',
            'valor' => 800000, 'saldo' => 800000, 'estado' => 'pendiente',
        ]);
    }

    private function iniciar(?int $monto = null): string
    {
        $inicio = $this->postJson("/api/v1/facturas/{$this->factura->id}/pago-en-linea", $monto ? ['monto' => $monto] : [])->assertCreated()->json('datos');
        $this->assertTrue($inicio['simulado']);
        $this->assertSame('/pagos/simulador?referencia='.rawurlencode($inicio['referencia']), $inicio['url']);

        return $inicio['referencia'];
    }

    public function test_el_cliente_paga_en_la_pasarela_de_prueba_y_el_pago_queda_en_la_factura(): void
    {
        $this->actingAs($this->usuarioCliente, 'sanctum');
        $this->getJson('/api/v1/pagos-en-linea/configuracion')->assertJsonPath('datos.habilitado', false)->assertJsonPath('datos.simulado', true);

        $referencia = $this->iniciar(300000);
        $this->getJson("/api/v1/pagos-en-linea/{$referencia}")->assertOk()
            ->assertJsonPath('datos.estado', 'pendiente')->assertJsonPath('datos.monto', 300000)
            ->assertJsonPath('datos.factura.numero', 'FV-950')->assertJsonPath('datos.correo', $this->usuarioCliente->correo);

        // «Dejar pendiente» y luego aprobar: el mismo número de transacción.
        $pendiente = $this->postJson("/api/v1/pagos-en-linea/{$referencia}/simular", ['resultado' => 'pendiente', 'banco' => 'Banco de prueba'])->assertOk()->json('datos');
        $this->assertSame('pendiente', $pendiente['estado']);
        $this->assertStringStartsWith('SIM-', $pendiente['transaccion_id']);
        $aprobado = $this->postJson("/api/v1/pagos-en-linea/{$referencia}/simular", ['resultado' => 'aprobado', 'banco' => 'Banco de prueba'])->assertOk()->json('datos');
        $this->assertSame($pendiente['transaccion_id'], $aprobado['transaccion_id']);

        // La página de resultado confirma sin consultar a Wompi.
        $this->postJson('/api/v1/pagos-en-linea/confirmar', ['transaccion_id' => $aprobado['transaccion_id']])->assertOk()
            ->assertJsonPath('datos.estado', 'aprobado')->assertJsonPath('datos.simulado', true)->assertJsonPath('datos.banco', 'Banco de prueba');

        $factura = $this->factura->fresh();
        $this->assertSame('parcial', $factura->estado);
        $this->assertEquals(500000, (float) $factura->saldo);
        $this->assertSame('SIMULADO-'.$aprobado['transaccion_id'], $factura->pagos()->where('medio', 'pse')->sole()->referencia);

        // Un pago terminado no se vuelve a simular, y queda en el historial del cliente.
        $this->postJson("/api/v1/pagos-en-linea/{$referencia}/simular", ['resultado' => 'aprobado'])->assertStatus(422);
        $this->getJson('/api/v1/pagos-en-linea')->assertOk()->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.estado', 'aprobado');
        $this->getJson('/api/v1/facturas?con_saldo=1')->assertOk()->assertJsonPath('datos.0.numero', 'FV-950');
    }

    public function test_un_pago_rechazado_no_cambia_la_factura(): void
    {
        $this->actingAs($this->usuarioCliente, 'sanctum');
        $referencia = $this->iniciar();
        $this->postJson("/api/v1/pagos-en-linea/{$referencia}/simular", ['resultado' => 'rechazado'])->assertOk()->assertJsonPath('datos.estado', 'rechazado');
        $this->postJson("/api/v1/pagos-en-linea/{$referencia}/simular", ['resultado' => 'cualquiera'])->assertStatus(422);

        $this->assertSame('pendiente', $this->factura->fresh()->estado);
        $this->assertSame(0, $this->factura->pagos()->count());
    }

    public function test_otro_cliente_no_ve_ni_responde_el_pago(): void
    {
        $this->actingAs($this->usuarioCliente, 'sanctum');
        $referencia = $this->iniciar();

        $otro = Usuario::factory()->create(['rol_id' => Rol::where('nombre', 'cliente')->value('id'), 'activo' => true, 'cliente_id' => Cliente::factory()->create()->id]);
        $this->actingAs($otro, 'sanctum');
        $this->getJson("/api/v1/pagos-en-linea/{$referencia}")->assertForbidden();
        $this->postJson("/api/v1/pagos-en-linea/{$referencia}/simular", ['resultado' => 'aprobado'])->assertForbidden();
        $this->getJson('/api/v1/pagos-en-linea')->assertOk()->assertJsonCount(0, 'datos');
        $this->assertSame(0, $this->factura->pagos()->count());
    }

    public function test_sin_activar_o_con_llaves_reales_no_hay_pasarela_de_prueba(): void
    {
        $this->actingAs($this->usuarioCliente, 'sanctum');
        $referencia = $this->iniciar();

        config(['services.wompi.simulado' => false]);
        $this->getJson('/api/v1/pagos-en-linea/configuracion')->assertJsonPath('datos.simulado', false);
        $this->postJson("/api/v1/facturas/{$this->factura->id}/pago-en-linea")->assertStatus(422);
        $this->postJson("/api/v1/pagos-en-linea/{$referencia}/simular", ['resultado' => 'aprobado'])->assertStatus(422);

        // Con llaves de Wompi se usa el checkout real aunque el simulador esté activado.
        config(['services.wompi.simulado' => true, 'services.wompi.llave_publica' => 'pub_test_abc', 'services.wompi.secreto_integridad' => 'secreto']);
        $this->getJson('/api/v1/pagos-en-linea/configuracion')->assertJsonPath('datos.habilitado', true)->assertJsonPath('datos.simulado', false);
        $this->assertStringStartsWith('https://', $this->postJson("/api/v1/facturas/{$this->factura->id}/pago-en-linea")->assertCreated()->json('datos.url'));
        $this->postJson("/api/v1/pagos-en-linea/{$referencia}/simular", ['resultado' => 'aprobado'])->assertStatus(422);
        $this->assertSame(0, $this->factura->pagos()->count());
    }
}
