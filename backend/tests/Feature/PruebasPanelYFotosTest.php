<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\MantenimientoProgramado;
use App\Models\Periodicidad;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\PlanService;
use Carbon\CarbonImmutable;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PeriodicidadSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fotos del ascensor, panel de operación, búsqueda global y hora de las visitas.
 * La fecha actual se fija en el lunes 5 de enero de 2026.
 */
class PruebasPanelYFotosTest extends TestCase
{
    use RefreshDatabase;

    /** PNG de 1x1 píxeles (las pruebas no dependen de la extensión GD). */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected Usuario $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-01-05 10:00:00'));
        Notification::fake();
        Storage::fake('local');
        $this->seed([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class]);
        $this->administrador = $this->usuarioConRol('administrador');
    }

    private function usuarioConRol(string $rol, array $datos = []): Usuario
    {
        return Usuario::factory()->create($datos + ['rol_id' => Rol::where('nombre', $rol)->value('id'), 'activo' => true]);
    }

    private function crearEquipo(?Cliente $cliente = null, array $datos = []): Equipo
    {
        $edificio = Edificio::factory()->create(['cliente_id' => ($cliente ?? Cliente::factory()->create())->id]);

        return Equipo::factory()->create($datos + ['edificio_id' => $edificio->id]);
    }

    private function foto(string $nombre = 'foto.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, base64_decode(self::PNG));
    }

    private function asignarPlan(Equipo $equipo, array $datos = []): void
    {
        $plan = app(PlanService::class)->crear([
            'nombre' => 'Plan '.$equipo->codigo,
            'activo' => true,
            'precios' => [['periodicidad_id' => Periodicidad::where('meses', 1)->value('id'), 'precio' => 100000]],
        ]);

        $this->actingAs($this->administrador, 'sanctum')->postJson('/api/v1/asignaciones', $datos + [
            'equipo_id' => $equipo->id,
            'plan_id' => $plan->id,
            'periodicidad_id' => Periodicidad::where('meses', 1)->value('id'),
            'fecha_inicio' => '2025-12-05',
        ])->assertCreated();
    }

    // ---------------------------------------------------------------- Fotos del ascensor

    public function test_sube_hasta_tres_fotos_del_ascensor(): void
    {
        $equipo = $this->crearEquipo();
        $this->actingAs($this->administrador, 'sanctum');

        $this->post("/api/v1/equipos/{$equipo->id}/fotos", ['fotos' => [$this->foto('a.png'), $this->foto('b.png')]], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonCount(2, 'datos')
            ->assertJsonPath('datos.0.nombre', 'a.png');

        // Ya hay 2: una tercera entra, dos más no.
        $this->post("/api/v1/equipos/{$equipo->id}/fotos", ['fotos' => [$this->foto(), $this->foto()]], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', 'El ascensor admite máximo 3 fotos. Puede agregar 1 más.');

        $this->post("/api/v1/equipos/{$equipo->id}/fotos", ['fotos' => [$this->foto('c.png')]], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(3, $equipo->fotos()->count());
        Storage::disk('local')->assertExists($equipo->fotos()->first()->ruta);
        $this->getJson("/api/v1/equipos/{$equipo->id}")->assertJsonCount(3, 'datos.fotos');
        $this->assertDatabaseHas('historial_equipos', ['equipo_id' => $equipo->id, 'evento' => 'fotos_agregadas']);
    }

    public function test_rechaza_archivos_que_no_son_imagenes_o_mas_de_tres(): void
    {
        $equipo = $this->crearEquipo();
        $this->actingAs($this->administrador, 'sanctum');

        $this->post("/api/v1/equipos/{$equipo->id}/fotos", [
            'fotos' => [UploadedFile::fake()->createWithContent('manual.pdf', '%PDF-1.4 prueba')],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fotos.0'], 'errores');

        $this->post("/api/v1/equipos/{$equipo->id}/fotos", [
            'fotos' => [$this->foto(), $this->foto(), $this->foto(), $this->foto()],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fotos'], 'errores');
    }

    public function test_la_foto_solo_la_descarga_quien_puede_ver_el_ascensor(): void
    {
        $cliente = Cliente::factory()->create();
        $equipo = $this->crearEquipo($cliente);
        $this->actingAs($this->administrador, 'sanctum')
            ->post("/api/v1/equipos/{$equipo->id}/fotos", ['fotos' => [$this->foto()]], ['Accept' => 'application/json'])
            ->assertCreated();
        $foto = $equipo->fotos()->first();
        $ruta = "/api/v1/equipos/{$equipo->id}/fotos/{$foto->id}/archivo";

        $this->get($ruta)->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->actingAs($this->usuarioConRol('cliente', ['cliente_id' => $cliente->id]), 'sanctum')->get($ruta)->assertOk();

        $ajeno = $this->usuarioConRol('cliente', ['cliente_id' => Cliente::factory()->create()->id]);
        $this->actingAs($ajeno, 'sanctum')->getJson($ruta)->assertForbidden();
        $this->actingAs($this->usuarioConRol('tecnico'), 'sanctum')
            ->post("/api/v1/equipos/{$equipo->id}/fotos", ['fotos' => [$this->foto()]], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_elimina_una_foto_y_su_archivo(): void
    {
        $equipo = $this->crearEquipo();
        $this->actingAs($this->administrador, 'sanctum')
            ->post("/api/v1/equipos/{$equipo->id}/fotos", ['fotos' => [$this->foto()]], ['Accept' => 'application/json']);
        $foto = $equipo->fotos()->first();

        $this->deleteJson("/api/v1/equipos/{$equipo->id}/fotos/{$foto->id}")->assertOk();

        $this->assertDatabaseMissing('fotos_equipo', ['id' => $foto->id]);
        Storage::disk('local')->assertMissing($foto->ruta);
    }

    // ---------------------------------------------------------------- Hora de las visitas

    public function test_las_visitas_toman_la_hora_del_plan_y_se_reprograman_con_hora(): void
    {
        $equipo = $this->crearEquipo();
        $this->asignarPlan($equipo, ['hora_preferida' => '10:30']);
        $visita = MantenimientoProgramado::where('equipo_id', $equipo->id)->orderBy('fecha_programada')->first();

        $this->getJson("/api/v1/mantenimientos/{$visita->id}")->assertJsonPath('datos.hora', '10:30');

        $this->patchJson("/api/v1/mantenimientos/{$visita->id}/reprogramar", ['fecha' => '2026-01-07', 'hora' => '19:30', 'motivo' => 'Solicitud del cliente'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hora'], 'errores');

        $this->patchJson("/api/v1/mantenimientos/{$visita->id}/reprogramar", ['fecha' => '2026-01-07', 'hora' => '14:00', 'motivo' => 'Solicitud del cliente'])
            ->assertOk()
            ->assertJsonPath('datos.hora', '14:00')
            ->assertJsonPath('datos.fecha_programada', '2026-01-07');
    }

    // ---------------------------------------------------------------- Panel de operación

    public function test_panel_de_operacion_con_datos_reales(): void
    {
        $equipo = $this->crearEquipo();
        $this->asignarPlan($equipo);
        $this->usuarioConRol('tecnico', ['nombre' => 'Andrés Rojas']);
        // Una visita atrasada (ayer) y la visita del 5 de enero es la cola de hoy.
        MantenimientoProgramado::where('equipo_id', $equipo->id)->orderBy('fecha_programada')->first()
            ->update(['fecha_programada' => '2026-01-02']);

        $respuesta = $this->actingAs($this->administrador, 'sanctum')->getJson('/api/v1/panel')->assertOk();

        $respuesta->assertJsonPath('datos.franja.tecnicos_activos', 1)
            ->assertJsonPath('datos.franja.proximo_festivo.fecha', '2026-01-12')
            ->assertJsonPath('datos.indicadores.atrasados.total', 1)
            ->assertJsonPath('datos.indicadores.ascensores_activos.total', 1)
            ->assertJsonPath('datos.estado_ascensores.0.codigo', $equipo->codigo)
            ->assertJsonPath('datos.estado_ascensores.0.atrasado', true)
            ->assertJsonPath('datos.tecnicos.0.nombre', 'Andrés Rojas')
            ->assertJsonPath('datos.alertas.0.tipo', 'peligro')
            ->assertJsonCount(9, 'datos.semanas');

        $this->assertTrue($respuesta->json('datos.semanas.8.actual'));
        $this->assertSame(0.0, (float) $respuesta->json('datos.indicadores.al_dia.porcentaje'));

        $this->actingAs($this->usuarioConRol('tecnico'), 'sanctum')->getJson('/api/v1/panel')->assertForbidden();
    }

    // ---------------------------------------------------------------- Búsqueda global

    public function test_busqueda_global_respeta_permisos_y_cliente(): void
    {
        $propio = Cliente::factory()->create(['nombre' => 'Conjunto Mirador']);
        $this->crearEquipo($propio, ['codigo' => 'ASC-777']);
        $this->crearEquipo(null, ['codigo' => 'ASC-778']);

        $this->actingAs($this->administrador, 'sanctum')
            ->getJson('/api/v1/buscar?q=asc-77')
            ->assertOk()
            ->assertJsonCount(2, 'datos.ascensores')
            ->assertJsonPath('datos.ascensores.0.enlace', '/equipos/'.Equipo::where('codigo', 'ASC-777')->value('id'));

        $this->getJson('/api/v1/buscar?q=mirador')->assertJsonPath('datos.clientes.0.titulo', 'Conjunto Mirador');
        $this->getJson('/api/v1/buscar?q=a')->assertUnprocessable();

        $usuarioCliente = $this->usuarioConRol('cliente', ['cliente_id' => $propio->id]);
        $this->actingAs($usuarioCliente, 'sanctum')
            ->getJson('/api/v1/buscar?q=asc-77')
            ->assertJsonCount(1, 'datos.ascensores')
            ->assertJsonPath('datos.ascensores.0.titulo', 'ASC-777');

        // El técnico no tiene clientes.ver: esa sección no aparece.
        $this->actingAs($this->usuarioConRol('tecnico'), 'sanctum')
            ->getJson('/api/v1/buscar?q=mirador')
            ->assertJsonMissingPath('datos.clientes');
    }
}
