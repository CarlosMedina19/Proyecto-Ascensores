<?php

namespace Tests\Feature;

use App\Models\Certificacion;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Emergencia;
use App\Models\Equipo;
use App\Models\Factura;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Control de acceso entre clientes (OWASP A01, IDOR): un cliente no puede ver ni modificar datos de otro
 * cambiando el identificador en la URL o en los filtros.
 *
 * Complementa test_cliente_solo_consulta_sus_propios_datos (clientes, edificios y ascensores) con la
 * información comercial y de servicio.
 */
class PruebasAislamientoClientesTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $propio;

    private Cliente $ajeno;

    private Equipo $equipoPropio;

    private Equipo $equipoAjeno;

    private Usuario $usuarioCliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolSeeder::class);
        $this->seed(PermisoSeeder::class);

        $this->propio = Cliente::create(['tipo' => 'juridica', 'nombre' => 'Propio', 'nit' => '900000001-1']);
        $this->ajeno = Cliente::create(['tipo' => 'juridica', 'nombre' => 'Ajeno', 'nit' => '900000002-2']);
        $this->equipoPropio = $this->crearEquipo($this->propio, 'ASC-P');
        $this->equipoAjeno = $this->crearEquipo($this->ajeno, 'ASC-A');

        $this->usuarioCliente = Usuario::factory()->create([
            'password' => 'clave12345',
            'rol_id' => Rol::where('nombre', 'cliente')->value('id'),
            'cliente_id' => $this->propio->id,
            'activo' => true,
        ]);
        $this->actingAs($this->usuarioCliente, 'sanctum');
    }

    private function crearEquipo(Cliente $cliente, string $codigo): Equipo
    {
        return $cliente->edificios()->create([
            'nombre' => "Edificio {$codigo}", 'direccion' => 'Calle 1', 'ciudad' => 'Bogotá',
            'nombre_contacto' => 'X', 'telefono_contacto' => '1',
        ])->equipos()->create(['codigo' => $codigo, 'tipo' => 'ascensor']);
    }

    private function crearFactura(Cliente $cliente, string $numero): Factura
    {
        return Factura::create([
            'cliente_id' => $cliente->id, 'numero' => $numero, 'fecha_emision' => now()->subDays(40)->toDateString(),
            'fecha_vencimiento' => now()->subDays(10)->toDateString(), 'valor' => 500000, 'saldo' => 500000, 'estado' => 'pendiente',
        ]);
    }

    private function crearCotizacion(Cliente $cliente, Equipo $equipo, string $numero): Cotizacion
    {
        return Cotizacion::create([
            'numero' => $numero, 'equipo_id' => $equipo->id, 'cliente_id' => $cliente->id, 'titulo' => 'Cambio de cables',
            'subtotal' => 1000000, 'iva' => 190000, 'total' => 1190000, 'fecha_emision' => now()->toDateString(),
            'vigente_hasta' => now()->addDays(30)->toDateString(), 'estado' => 'enviada',
        ]);
    }

    private function crearEmergencia(Equipo $equipo, string $numero): Emergencia
    {
        return Emergencia::create([
            'numero' => $numero, 'equipo_id' => $equipo->id, 'tipo' => 'falla', 'origen' => 'panel',
            'personas_atrapadas' => false, 'estado' => 'reportada', 'minutos_objetivo' => 60, 'reportada_en' => now(),
        ]);
    }

    public function test_el_cliente_no_ve_facturas_ni_cartera_de_otro_cliente(): void
    {
        $this->crearFactura($this->propio, 'FV-PROPIA');
        $ajena = $this->crearFactura($this->ajeno, 'FV-AJENA');

        $this->getJson('/api/v1/facturas')->assertOk()
            ->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.numero', 'FV-PROPIA');
        // Pedir el filtro de otro cliente no amplía lo que puede ver.
        $this->getJson("/api/v1/facturas?cliente_id={$this->ajeno->id}")->assertOk()->assertJsonCount(0, 'datos');
        $this->getJson("/api/v1/facturas/{$ajena->id}")->assertForbidden();

        $resumenPropio = $this->getJson('/api/v1/cartera/resumen')->assertOk()->json('datos');
        $this->getJson("/api/v1/cartera/resumen?cliente_id={$this->ajeno->id}")->assertOk()
            ->assertJsonPath('datos', $resumenPropio);
    }

    public function test_el_cliente_no_ve_ni_responde_cotizaciones_de_otro_cliente(): void
    {
        $this->crearCotizacion($this->propio, $this->equipoPropio, 'COT-PROPIA');
        $ajena = $this->crearCotizacion($this->ajeno, $this->equipoAjeno, 'COT-AJENA');

        $numeros = collect($this->getJson('/api/v1/cotizaciones')->assertOk()->json('datos'))->pluck('numero');
        $this->assertContains('COT-PROPIA', $numeros);
        $this->assertNotContains('COT-AJENA', $numeros);

        $this->getJson("/api/v1/cotizaciones/{$ajena->id}")->assertForbidden();
        $this->postJson("/api/v1/cotizaciones/{$ajena->id}/respuesta", ['aprobar' => true])->assertForbidden();
        $this->assertSame('enviada', $ajena->fresh()->estado);
    }

    public function test_el_cliente_no_ve_emergencias_de_otro_cliente(): void
    {
        $this->crearEmergencia($this->equipoPropio, 'EM-PROPIA');
        $ajena = $this->crearEmergencia($this->equipoAjeno, 'EM-AJENA');

        $numeros = collect($this->getJson('/api/v1/emergencias')->assertOk()->json('datos'))->pluck('numero');
        $this->assertContains('EM-PROPIA', $numeros);
        $this->assertNotContains('EM-AJENA', $numeros);

        $this->getJson("/api/v1/emergencias/{$ajena->id}")->assertForbidden();
    }

    public function test_el_cliente_no_accede_a_certificaciones_documentos_ni_qr_de_otro_ascensor(): void
    {
        $certificacion = Certificacion::create([
            'equipo_id' => $this->equipoAjeno->id, 'organismo' => 'Inspecciones Andinas',
            'fecha_inspeccion' => now()->subMonth()->toDateString(), 'fecha_vencimiento' => now()->addYear()->toDateString(),
            'resultado' => 'conforme',
        ]);

        $this->getJson("/api/v1/equipos/{$this->equipoAjeno->id}/certificaciones")->assertForbidden();
        $this->getJson("/api/v1/certificaciones/{$certificacion->id}/archivo")->assertForbidden();
        $this->getJson("/api/v1/equipos/{$this->equipoAjeno->id}/documentos")->assertForbidden();
        $this->getJson("/api/v1/equipos/{$this->equipoAjeno->id}/qr")->assertForbidden();

        $this->getJson("/api/v1/equipos/{$this->equipoPropio->id}/certificaciones")->assertOk();
    }

    public function test_el_cliente_no_consulta_indicadores_de_otro_ascensor(): void
    {
        $this->getJson("/api/v1/indicadores/servicio?equipo_id={$this->equipoAjeno->id}")->assertForbidden();
        $this->getJson("/api/v1/indicadores/servicio?equipo_id={$this->equipoPropio->id}")->assertOk();
    }

    public function test_un_usuario_no_puede_marcar_notificaciones_de_otro(): void
    {
        $otro = Usuario::factory()->create([
            'rol_id' => Rol::where('nombre', 'cliente')->value('id'),
            'cliente_id' => $this->ajeno->id,
            'activo' => true,
        ]);
        $notificacion = $otro->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => 'aviso', 'data' => ['mensaje' => 'Privado'],
        ]);

        $this->getJson('/api/v1/notificaciones')->assertOk()->assertDontSee('Privado');
        $this->patchJson("/api/v1/notificaciones/{$notificacion->id}/leer")->assertNotFound();
        $this->assertNull($notificacion->fresh()->read_at);
    }
}
