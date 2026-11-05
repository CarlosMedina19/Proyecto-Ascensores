<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\PagoEnLinea;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\PagoEnLineaService;
use Carbon\CarbonImmutable;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Pago de facturas por PSE con Wompi (modo de pruebas). */
class PruebasPagoPseTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $usuarioCliente;

    private Factura $factura;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00:00'));
        Notification::fake();
        $this->seed([RolSeeder::class, PermisoSeeder::class, ConfiguracionSeeder::class]);
        config(['services.wompi' => [
            'llave_publica' => 'pub_test_abc', 'secreto_integridad' => 'test_integrity_xyz', 'secreto_eventos' => 'test_events_123',
            'api' => 'https://sandbox.wompi.co/v1', 'checkout' => 'https://checkout.wompi.co/p/', 'retorno' => 'http://localhost:5173/pagos/resultado',
        ]]);
        $cliente = Cliente::factory()->create();
        $this->usuarioCliente = Usuario::factory()->create(['rol_id' => Rol::where('nombre', 'cliente')->value('id'), 'activo' => true, 'cliente_id' => $cliente->id]);
        $this->factura = Factura::create([
            'cliente_id' => $cliente->id, 'numero' => 'FV-900', 'fecha_emision' => '2026-11-01', 'fecha_vencimiento' => '2026-11-30',
            'valor' => 500000, 'saldo' => 500000, 'estado' => 'pendiente',
        ]);
    }

    private function transaccion(string $referencia, string $estado, int $centavos = 50000000): array
    {
        return ['id' => '11-'.$estado, 'reference' => $referencia, 'status' => $estado, 'amount_in_cents' => $centavos, 'currency' => 'COP', 'payment_method_type' => 'PSE'];
    }

    public function test_el_cliente_paga_su_factura_por_pse_y_el_pago_se_registra_una_vez(): void
    {
        $this->actingAs($this->usuarioCliente, 'sanctum');
        $this->getJson('/api/v1/pagos-en-linea/configuracion')->assertJsonPath('datos.habilitado', true)->assertJsonPath('datos.pruebas', true);

        $inicio = $this->postJson("/api/v1/facturas/{$this->factura->id}/pago-en-linea")->assertCreated()->json('datos');
        $referencia = $inicio['referencia'];
        $this->assertStringStartsWith('https://checkout.wompi.co/p/?public-key=pub_test_abc', $inicio['url']);
        $this->assertStringContainsString('amount-in-cents=50000000', $inicio['url']);
        $this->assertStringContainsString('signature%3Aintegrity='.hash('sha256', "{$referencia}50000000COPtest_integrity_xyz"), $inicio['url']);

        Http::fake(['sandbox.wompi.co/v1/transactions/*' => Http::response(['data' => $this->transaccion($referencia, 'APPROVED')])]);
        $this->postJson('/api/v1/pagos-en-linea/confirmar', ['transaccion_id' => '11-APPROVED'])->assertOk()->assertJsonPath('datos.estado', 'aprobado');
        $this->postJson('/api/v1/pagos-en-linea/confirmar', ['transaccion_id' => '11-APPROVED'])->assertOk();

        $this->assertSame('pagada', $this->factura->fresh()->estado);
        $this->assertSame(1, $this->factura->pagos()->where('medio', 'pse')->count());
    }

    public function test_un_monto_distinto_o_un_evento_sin_firma_valida_no_registran_pagos(): void
    {
        $this->actingAs($this->usuarioCliente, 'sanctum');
        $referencia = $this->postJson("/api/v1/facturas/{$this->factura->id}/pago-en-linea", ['monto' => 200000])->assertCreated()->json('datos.referencia');
        $this->postJson("/api/v1/facturas/{$this->factura->id}/pago-en-linea", ['monto' => 900000])->assertStatus(422);

        Http::fake(['*' => Http::response(['data' => $this->transaccion($referencia, 'APPROVED', 100)])]);
        $this->postJson('/api/v1/pagos-en-linea/confirmar', ['transaccion_id' => 'x'])->assertStatus(422);

        $transaccion = $this->transaccion($referencia, 'APPROVED', 20000000);
        $evento = ['event' => 'transaction.updated', 'data' => ['transaction' => $transaccion], 'timestamp' => 1730700000,
            'signature' => ['properties' => ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'], 'checksum' => 'falsa']];
        $this->postJson('/api/v1/publico/wompi/eventos', $evento)->assertStatus(400);
        $this->assertSame(0, $this->factura->pagos()->count());

        // Con la firma correcta el evento registra el abono.
        $evento['signature']['checksum'] = hash('sha256', $transaccion['id'].'APPROVED'.'20000000'.'1730700000'.'test_events_123');
        $this->postJson('/api/v1/publico/wompi/eventos', $evento)->assertOk();
        $this->assertSame('parcial', $this->factura->fresh()->estado);
        $this->assertEquals(300000, $this->factura->fresh()->saldo);
        $this->assertSame('aprobado', PagoEnLinea::where('referencia', $referencia)->value('estado'));
    }

    public function test_sin_llaves_no_se_ofrece_y_un_cliente_no_paga_facturas_ajenas(): void
    {
        $ajena = Factura::create([
            'cliente_id' => Cliente::factory()->create()->id, 'numero' => 'FV-901', 'fecha_emision' => '2026-11-01', 'fecha_vencimiento' => '2026-11-30',
            'valor' => 100000, 'saldo' => 100000, 'estado' => 'pendiente',
        ]);
        $this->actingAs($this->usuarioCliente, 'sanctum')->postJson("/api/v1/facturas/{$ajena->id}/pago-en-linea")->assertForbidden();

        config(['services.wompi.llave_publica' => null]);
        $this->assertFalse(PagoEnLineaService::habilitado());
        $this->postJson("/api/v1/facturas/{$this->factura->id}/pago-en-linea")->assertStatus(422);
    }
}
