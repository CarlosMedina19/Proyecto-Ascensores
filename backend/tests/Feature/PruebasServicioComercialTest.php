<?php

namespace Tests\Feature;

use App\Models\Certificacion;
use App\Models\Cliente;
use App\Models\Emergencia;
use App\Models\EnvioNotificacion;
use App\Models\Equipo;
use App\Models\Rol;
use App\Models\Tecnico;
use App\Models\Usuario;
use App\Services\CertificacionService;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fase 6: certificación anual, emergencias 24/7, reporte por QR sin sesión, cotizaciones que el cliente
 * aprueba o rechaza e indicadores del servicio.
 */
class PruebasServicioComercialTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $administrador;

    private Cliente $cliente;

    private Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Bus::fake();
        Storage::fake('local');
        $this->seed([RolSeeder::class, PermisoSeeder::class]);
        $this->administrador = $this->crearUsuario('administrador');
        $this->cliente = Cliente::create(['tipo' => 'juridica', 'nombre' => 'Conjunto Mirador', 'nit' => '900123456-1', 'estado' => true]);
        $edificio = $this->cliente->edificios()->create([
            'tipo' => 'residencial', 'nombre' => 'Torre A', 'direccion' => 'Calle 10 # 5-20', 'ciudad' => 'Bogotá',
            'nombre_contacto' => 'Ana Torres', 'telefono_contacto' => '3001112233',
        ]);
        $this->equipo = $edificio->equipos()->create(['codigo' => 'ASC-100', 'tipo' => 'ascensor', 'estado' => 'activo'])->load('edificio');
    }

    private function crearUsuario(string $rol, array $datos = []): Usuario
    {
        return Usuario::factory()->create($datos + ['rol_id' => Rol::where('nombre', $rol)->firstOrFail()->id, 'activo' => true]);
    }

    private function crearTecnico(): Tecnico
    {
        $usuario = $this->crearUsuario('tecnico', ['nombre' => 'Andrés Rojas']);

        return Tecnico::create(['usuario_id' => $usuario->id, 'tipo_documento' => 'CC', 'numero_documento' => '1012345678', 'telefono' => '3105556677', 'disponibilidad' => 'disponible']);
    }

    // ---------- Certificación anual ----------

    public function test_registra_la_certificacion_con_su_certificado_y_calcula_el_estado(): void
    {
        $this->actingAs($this->administrador, 'sanctum')
            ->post("/api/v1/equipos/{$this->equipo->id}/certificaciones", [
                'organismo' => 'Inspecciones Andinas S.A.S.',
                'numero_certificado' => 'RA-2026-118',
                'fecha_inspeccion' => now()->subMonths(11)->toDateString(),
                'fecha_vencimiento' => now()->addDays(25)->toDateString(),
                'resultado' => 'conforme',
                'archivo' => UploadedFile::fake()->create('certificado.pdf', 120, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('datos.estado', 'por_vencer')
            ->assertJsonPath('datos.norma', 'NTC 5926-1')
            ->assertJsonPath('datos.tiene_archivo', true);

        $this->actingAs($this->administrador, 'sanctum')
            ->getJson('/api/v1/certificaciones')
            ->assertOk()
            ->assertJsonPath('datos.0.estado', 'por_vencer')
            ->assertJsonPath('resumen.por_vencer', 1);

        $this->assertDatabaseHas('historial_equipos', ['equipo_id' => $this->equipo->id, 'evento' => 'certificacion_registrada']);
    }

    public function test_rechaza_vencimiento_anterior_a_la_inspeccion_y_archivos_no_permitidos(): void
    {
        $this->actingAs($this->administrador, 'sanctum')
            ->post("/api/v1/equipos/{$this->equipo->id}/certificaciones", [
                'organismo' => 'Inspecciones Andinas',
                'fecha_inspeccion' => '2026-03-01',
                'fecha_vencimiento' => '2026-02-01',
                'resultado' => 'conforme',
                'archivo' => UploadedFile::fake()->create('virus.exe', 10),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_vencimiento', 'archivo'], 'errores');
    }

    public function test_avisa_una_sola_vez_por_umbral_antes_del_vencimiento(): void
    {
        $this->crearUsuario('coordinador');
        Certificacion::create([
            'equipo_id' => $this->equipo->id, 'organismo' => 'Inspecciones Andinas', 'fecha_inspeccion' => now()->subYear()->addDays(20),
            'fecha_vencimiento' => now()->addDays(20)->toDateString(), 'resultado' => 'conforme',
        ]);

        $servicio = app(CertificacionService::class);
        $this->assertGreaterThan(0, $servicio->enviarAlertas());
        $this->assertSame(0, $servicio->enviarAlertas(), 'El mismo umbral no se vuelve a avisar.');
        $this->assertTrue(EnvioNotificacion::where('tipo', 'like', 'certificacion_30d_%')->exists());
    }

    // ---------- Emergencias ----------

    public function test_reporte_por_qr_sin_sesion_crea_la_emergencia_con_tiempo_objetivo(): void
    {
        $this->getJson("/api/v1/publico/ascensores/{$this->equipo->codigo_qr}")
            ->assertOk()
            ->assertJsonPath('datos.codigo', 'ASC-100')
            ->assertJsonMissingPath('datos.id');

        $this->postJson("/api/v1/publico/ascensores/{$this->equipo->codigo_qr}/reportes", [
            'tipo' => 'atrapamiento',
            'reportante_nombre' => 'Portería Torre A',
            'reportante_telefono' => '601 555 1234',
            'descripcion' => 'Cabina detenida entre el piso 3 y el 4.',
        ])->assertCreated()->assertJsonPath('datos.minutos_objetivo', 30);

        $emergencia = Emergencia::firstOrFail();
        $this->assertSame('qr', $emergencia->origen);
        $this->assertTrue($emergencia->personas_atrapadas);
        $this->assertMatchesRegularExpression('/^EM-\d{4}-00001$/', $emergencia->numero);
    }

    public function test_el_qr_publico_exige_contacto_no_acepta_codigos_inventados_y_limita_envios(): void
    {
        RateLimiter::clear('qr-ip|127.0.0.1');
        $this->postJson("/api/v1/publico/ascensores/{$this->equipo->codigo_qr}/reportes", ['tipo' => 'falla'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reportante_nombre', 'reportante_telefono'], 'errores');
        $this->postJson('/api/v1/publico/ascensores/codigo-inventado/reportes', ['tipo' => 'falla', 'reportante_nombre' => 'X', 'reportante_telefono' => '3001234567'])
            ->assertNotFound();
        $valido = ['tipo' => 'falla', 'reportante_nombre' => 'Portería', 'reportante_telefono' => '3001234567'];
        $this->postJson("/api/v1/publico/ascensores/{$this->equipo->codigo_qr}/reportes", $valido)->assertCreated();
        // Tres envíos por minuto desde la misma dirección; el cuarto se bloquea.
        $this->postJson("/api/v1/publico/ascensores/{$this->equipo->codigo_qr}/reportes", $valido)
            ->assertStatus(429);
    }

    public function test_la_emergencia_avanza_por_estados_y_mide_el_tiempo_de_respuesta(): void
    {
        $tecnico = $this->crearTecnico();
        $respuesta = $this->actingAs($this->administrador, 'sanctum')
            ->postJson('/api/v1/emergencias', ['equipo_id' => $this->equipo->id, 'tipo' => 'falla', 'descripcion' => 'No abre puertas en el piso 2.'])
            ->assertCreated()
            ->assertJsonPath('datos.minutos_objetivo', 60);
        $id = $respuesta->json('datos.id');

        $this->actingAs($this->administrador, 'sanctum')->postJson("/api/v1/emergencias/{$id}/estado", ['estado' => 'asignada'])
            ->assertUnprocessable();
        $this->actingAs($this->administrador, 'sanctum')->postJson("/api/v1/emergencias/{$id}/estado", ['estado' => 'asignada', 'tecnico_id' => $tecnico->id])
            ->assertOk()->assertJsonPath('datos.tecnico.nombre', 'Andrés Rojas');

        $this->travel(25)->minutes();
        // El técnico asignado marca la llegada y la solución (permiso emergencias.atender).
        $this->actingAs($tecnico->usuario, 'sanctum')->postJson("/api/v1/emergencias/{$id}/estado", ['estado' => 'en_sitio'])
            ->assertOk()->assertJsonPath('datos.minutos_respuesta', 25)->assertJsonPath('datos.dentro_objetivo', true);
        $this->actingAs($tecnico->usuario, 'sanctum')->postJson("/api/v1/emergencias/{$id}/estado", ['estado' => 'resuelta'])
            ->assertUnprocessable();
        $this->actingAs($tecnico->usuario, 'sanctum')->postJson("/api/v1/emergencias/{$id}/estado", ['estado' => 'resuelta', 'solucion' => 'Se ajustó el operador de puertas.'])
            ->assertOk()->assertJsonPath('datos.estado', 'resuelta')->assertJsonCount(4, 'datos.seguimiento');
        $this->actingAs($tecnico->usuario, 'sanctum')->postJson("/api/v1/emergencias/{$id}/estado", ['estado' => 'cancelada', 'nota' => 'x'])
            ->assertForbidden();

        $indicadores = $this->actingAs($this->administrador, 'sanctum')->getJson('/api/v1/indicadores/servicio')->assertOk()->json('datos');
        $this->assertSame(25, $indicadores['respuesta']['minutos_promedio']);
        $this->assertSame(1, $indicadores['respuesta']['dentro_objetivo']);
        $this->assertSame(1, $indicadores['mtbf']['fallas']);
        $this->assertLessThan(100, $indicadores['disponibilidad']['porcentaje']);
    }

    public function test_el_cliente_solo_ve_las_emergencias_de_sus_ascensores(): void
    {
        $otro = Cliente::create(['tipo' => 'juridica', 'nombre' => 'Otro', 'nit' => '900999888-1', 'estado' => true]);
        $usuarioCliente = $this->crearUsuario('cliente', ['cliente_id' => $otro->id]);
        $this->actingAs($this->administrador, 'sanctum')->postJson('/api/v1/emergencias', ['equipo_id' => $this->equipo->id, 'tipo' => 'ruido'])->assertCreated();

        $this->actingAs($usuarioCliente, 'sanctum')->getJson('/api/v1/emergencias')->assertOk()->assertJsonCount(0, 'datos');
        $this->actingAs($usuarioCliente, 'sanctum')->postJson('/api/v1/emergencias', ['equipo_id' => $this->equipo->id, 'tipo' => 'falla'])->assertForbidden();
    }

    // ---------- Cotizaciones ----------

    public function test_el_cliente_aprueba_o_rechaza_su_cotizacion_una_sola_vez(): void
    {
        $usuarioCliente = $this->crearUsuario('cliente', ['cliente_id' => $this->cliente->id]);
        $id = $this->actingAs($this->administrador, 'sanctum')->postJson('/api/v1/cotizaciones', [
            'equipo_id' => $this->equipo->id,
            'titulo' => 'Cambio de amortiguadores del foso',
            'iva_porcentaje' => 19,
            'items' => [
                ['descripcion' => 'Amortiguador hidráulico', 'cantidad' => 2, 'valor_unitario' => 850000],
                ['descripcion' => 'Mano de obra', 'cantidad' => 1, 'valor_unitario' => 300000],
            ],
        ])->assertCreated()
            ->assertJsonPath('datos.subtotal', 2000000)
            ->assertJsonPath('datos.total', 2380000)
            ->assertJsonPath('datos.estado', 'enviada')
            ->json('datos.id');

        $this->actingAs($usuarioCliente, 'sanctum')->postJson("/api/v1/cotizaciones/{$id}/respuesta", ['decision' => 'rechazada'])
            ->assertUnprocessable();
        $this->actingAs($usuarioCliente, 'sanctum')->postJson("/api/v1/cotizaciones/{$id}/respuesta", ['decision' => 'aprobada'])
            ->assertOk()->assertJsonPath('datos.estado', 'aprobada');
        $this->actingAs($usuarioCliente, 'sanctum')->postJson("/api/v1/cotizaciones/{$id}/respuesta", ['decision' => 'rechazada', 'motivo' => 'Cambio de opinión'])
            ->assertUnprocessable();

        $ajeno = $this->crearUsuario('cliente', ['cliente_id' => Cliente::create(['tipo' => 'juridica', 'nombre' => 'Ajeno', 'nit' => '800111222-3', 'estado' => true])->id]);
        $this->actingAs($ajeno, 'sanctum')->getJson("/api/v1/cotizaciones/{$id}")->assertForbidden();
        // El coordinador no responde por el cliente.
        $this->actingAs($this->crearUsuario('coordinador'), 'sanctum')->postJson("/api/v1/cotizaciones/{$id}/respuesta", ['decision' => 'aprobada'])->assertForbidden();
    }

    public function test_documentos_del_ascensor_incluyen_el_certificado(): void
    {
        app(CertificacionService::class);
        $this->actingAs($this->administrador, 'sanctum')
            ->post("/api/v1/equipos/{$this->equipo->id}/certificaciones", [
                'organismo' => 'Inspecciones Andinas', 'fecha_inspeccion' => now()->subMonth()->toDateString(),
                'fecha_vencimiento' => now()->addMonths(11)->toDateString(), 'resultado' => 'condicionado',
                'archivo' => UploadedFile::fake()->create('cert.pdf', 50, 'application/pdf'),
            ], ['Accept' => 'application/json'])->assertCreated();

        $this->actingAs($this->administrador, 'sanctum')->getJson("/api/v1/equipos/{$this->equipo->id}/documentos")
            ->assertOk()->assertJsonPath('datos.0.tipo', 'certificado');
        $this->actingAs($this->administrador, 'sanctum')->getJson("/api/v1/equipos/{$this->equipo->id}/qr")
            ->assertOk()->assertJsonPath('datos.codigo_qr', $this->equipo->codigo_qr);
    }
}
