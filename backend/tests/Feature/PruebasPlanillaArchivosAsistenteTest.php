<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\ChecklistSeccion;
use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\Periodicidad;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\VisitaTecnica;
use App\Notifications\VisitaTecnicaNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\EspecialidadSeeder;
use Database\Seeders\PeriodicidadSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as SolicitudHttp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/** Planilla de asistencia técnica editable, visitas con PDF, Object Storage y asistentes. */
class PruebasPlanillaArchivosAsistenteTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private Usuario $administrador;

    private Usuario $tecnico;

    private Usuario $usuarioCliente;

    private Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:00:00'));
        Notification::fake();
        Storage::fake('local');
        $this->seed([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class, EspecialidadSeeder::class, ChecklistSeeder::class]);
        $rol = fn ($nombre) => Rol::where('nombre', $nombre)->value('id');
        $this->administrador = Usuario::factory()->create(['rol_id' => $rol('administrador'), 'activo' => true]);
        $cliente = Cliente::factory()->create();
        $this->usuarioCliente = Usuario::factory()->create(['rol_id' => $rol('cliente'), 'activo' => true, 'cliente_id' => $cliente->id]);
        $this->equipo = Equipo::factory()->create(['edificio_id' => Edificio::factory()->create(['cliente_id' => $cliente->id])->id, 'estado' => 'activo']);

        $this->actingAs($this->administrador, 'sanctum');
        $usuarioId = $this->postJson('/api/v1/tecnicos', [
            'nombre' => 'Andrés Rojas', 'correo' => 'andres@sigma.test', 'password' => 'Clave1234', 'tipo_documento' => 'CC', 'numero_documento' => '10203040',
            'especialidades' => [Especialidad::value('id')],
        ])->assertCreated()->json('datos.usuario_id');
        $this->tecnico = Usuario::findOrFail($usuarioId);
        $this->postJson('/api/v1/asignaciones', [
            'equipo_id' => $this->equipo->id, 'periodicidad_id' => Periodicidad::where('meses', 1)->value('id'), 'fecha_inicio' => '2026-10-20',
        ])->assertCreated();
    }

    private function respuestas(array $cambios = []): array
    {
        return ChecklistItem::where('activo', true)->get()
            ->map(fn ($i) => array_merge(['item_id' => $i->id, 'ins' => true, 'estado' => 'B', 'na' => false], $cambios[$i->codigo] ?? []))
            ->values()->all();
    }

    private function visita(array $cambios = []): array
    {
        return [
            'tipo_servicio' => 'preventivo',
            'entrada_en' => now()->subHour()->toIso8601String(),
            'resultados' => $this->respuestas($cambios),
            'observaciones' => 'Equipo en buen estado general.',
            'repuestos' => [['nombre' => 'Zapata de nylon', 'cantidad' => 2]],
            'recibe_nombre' => 'Laura Gómez',
            'recibe_documento' => '52123456',
            'firma_tecnico' => 'data:image/png;base64,'.self::PNG,
            'firma_recibe' => 'data:image/png;base64,'.self::PNG,
        ];
    }

    // ---------------------------------------------------------------- Plantilla

    public function test_la_plantilla_trae_la_planilla_en_papel_y_solo_el_administrador_la_edita(): void
    {
        $plantilla = $this->getJson('/api/v1/planilla/plantilla')->assertOk()->json('datos');
        $this->assertSame(['Cuarto de máquinas', 'Cabina', 'Contrapeso', 'Mecanismos de puertas', 'Recorrido de foso', 'En foso'], array_column($plantilla, 'nombre'));
        $this->assertSame(45, collect($plantilla)->sum(fn ($s) => count($s['items'])));

        // Agregar, renombrar, mover y quitar.
        $foso = collect($plantilla)->firstWhere('clave', 'foso');
        $datos = $this->postJson('/api/v1/planilla/items', ['seccion_id' => $foso['id'], 'nombre' => 'Verificación de la escalera de foso.', 'critico' => true])->assertCreated()->json('datos');
        $nuevo = collect(collect($datos)->firstWhere('clave', 'foso')['items'])->last();
        $this->assertSame('EF-05', $nuevo['codigo']);
        $this->assertTrue($nuevo['critico']);
        $this->patchJson("/api/v1/planilla/items/{$nuevo['id']}", ['nombre' => 'Escalera de foso.', 'mover' => 'arriba'])->assertOk();
        $this->assertSame('Escalera de foso.', ChecklistItem::find($nuevo['id'])->nombre);
        $this->assertSame(4, ChecklistItem::find($nuevo['id'])->orden);
        $this->deleteJson("/api/v1/planilla/items/{$nuevo['id']}")->assertOk();
        $this->assertNull(ChecklistItem::find($nuevo['id']), 'sin planillas que la usen, se borra');

        $seccion = collect($this->postJson('/api/v1/planilla/secciones', ['nombre' => 'Sala de bombas'])->assertCreated()->json('datos'))->last();
        $this->assertSame('sala_de_bombas', $seccion['clave']);
        $this->patchJson("/api/v1/planilla/secciones/{$seccion['id']}", ['nombre' => 'Sala de bombas y tanques'])->assertOk();
        $this->deleteJson("/api/v1/planilla/secciones/{$seccion['id']}")->assertOk();
        $this->assertFalse(ChecklistSeccion::find($seccion['id'])->activo);

        // El técnico la consulta pero no la cambia.
        $this->actingAs($this->tecnico, 'sanctum');
        $this->getJson('/api/v1/planilla/plantilla')->assertOk();
        $this->postJson('/api/v1/planilla/secciones', ['nombre' => 'Otra'])->assertForbidden();
        $this->deleteJson('/api/v1/planilla/items/'.ChecklistItem::value('id'))->assertForbidden();
    }

    // ---------------------------------------------------------------- Visita con PDF

    public function test_el_tecnico_diligencia_la_planilla_y_queda_el_pdf_en_la_ficha(): void
    {
        $this->actingAs($this->tecnico, 'sanctum');
        $this->postJson("/api/v1/qr/{$this->equipo->codigo_qr}/mantenimiento")->assertCreated();

        // Validaciones: todas las actividades marcadas y R/M con observación.
        $incompleta = $this->visita();
        array_pop($incompleta['resultados']);
        $this->postJson("/api/v1/equipos/{$this->equipo->id}/visitas", $incompleta)->assertStatus(422);
        $this->postJson("/api/v1/equipos/{$this->equipo->id}/visitas", $this->visita(['CB-01' => ['estado' => 'M']]))
            ->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'observación'));

        $respuesta = $this->postJson("/api/v1/equipos/{$this->equipo->id}/visitas", $this->visita([
            'CB-01' => ['estado' => 'M', 'observacion' => 'Espejo roto'],
            'RF-07' => ['na' => true, 'estado' => null, 'ins' => false],
        ]))->assertCreated();
        $this->assertSame('12901', $respuesta->json('datos.numero'));
        $this->assertSame(['B' => 43, 'R' => 0, 'M' => 1, 'NA' => 1], $respuesta->json('datos.conteo'));

        $visita = VisitaTecnica::firstOrFail();
        Storage::disk('local')->assertExists([$visita->pdf_ruta, $visita->firma_tecnico_ruta, $visita->firma_recibe_ruta]);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($visita->pdf_ruta));
        $this->assertNotNull($visita->orden_id, 'queda ligada a la orden en proceso');
        $this->assertSame('10203040', $visita->tecnico_documento);
        $this->assertDatabaseHas('historial_equipos', ['equipo_id' => $this->equipo->id, 'evento' => 'mantenimiento']);
        Notification::assertSentTo($this->administrador, VisitaTecnicaNotification::class);
        Notification::assertSentTo($this->usuarioCliente, VisitaTecnicaNotification::class);

        // El cliente ve la lista y descarga el PDF de su ascensor.
        $this->actingAs($this->usuarioCliente, 'sanctum');
        $this->getJson("/api/v1/equipos/{$this->equipo->id}/visitas")->assertOk()->assertJsonPath('datos.0.numero', '12901');
        $this->get("/api/v1/visitas/{$visita->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Pestaña Visitas de la app: cada quien ve las planillas que le corresponden.
        $this->getJson('/api/v1/visitas')->assertOk()->assertJsonCount(1, 'datos')
            ->assertJsonPath('datos.0.equipo.codigo', $this->equipo->codigo)->assertJsonPath('datos.0.ruta_pdf', "/visitas/{$visita->id}/pdf");
        $this->actingAs($this->tecnico, 'sanctum');
        $this->getJson('/api/v1/visitas')->assertOk()->assertJsonCount(1, 'datos');
        $otroCliente = Usuario::factory()->create(['rol_id' => Rol::where('nombre', 'cliente')->value('id'), 'activo' => true, 'cliente_id' => Cliente::factory()->create()->id]);
        $this->actingAs($otroCliente, 'sanctum');
        $this->getJson('/api/v1/visitas')->assertOk()->assertJsonCount(0, 'datos');
        $this->actingAs($this->usuarioCliente, 'sanctum');

        // Editar la plantilla después no cambia la planilla ya diligenciada.
        ChecklistItem::where('codigo', 'CB-01')->update(['nombre' => 'Otro nombre']);
        $this->assertSame('Verificación de cabina (falso techo, iluminación, espejo y piso).', collect($visita->fresh()->secciones[1]['items'])->firstWhere('codigo', 'CB-01')['nombre']);
    }

    // ---------------------------------------------------------------- Object Storage

    public function test_los_archivos_van_al_object_storage_configurado(): void
    {
        config(['filesystems.archivos' => 'objetos']);
        Storage::fake('objetos');

        $foto = UploadedFile::fake()->createWithContent('cabina.png', base64_decode(self::PNG));
        $this->post("/api/v1/equipos/{$this->equipo->id}/fotos", ['fotos' => [$foto]], ['Accept' => 'application/json'])->assertCreated();
        $ruta = $this->equipo->fotos()->value('ruta');
        Storage::disk('objetos')->assertExists($ruta);
        Storage::disk('local')->assertMissing($ruta);

        $id = $this->equipo->fotos()->value('id');
        $this->get("/api/v1/equipos/{$this->equipo->id}/fotos/{$id}/archivo")->assertOk()->assertHeader('Content-Type', 'image/png');

        // La migración copia lo local al bucket sin borrar el original.
        config(['filesystems.archivos' => 'local']);
        Storage::disk('local')->put('equipos/antigua.png', base64_decode(self::PNG));
        $this->equipo->fotos()->update(['ruta' => 'equipos/antigua.png']);
        $this->artisan('sigma:migrar-archivos')->assertSuccessful();
        Storage::disk('objetos')->assertExists('equipos/antigua.png');
        Storage::disk('local')->assertExists('equipos/antigua.png');
        $this->artisan('sigma:migrar-archivos')->assertSuccessful();
    }

    // ---------------------------------------------------------------- Asistentes

    public function test_el_asistente_publico_da_informacion_y_contacto(): void
    {
        $this->app['auth']->forgetGuards();
        $datos = $this->postJson('/api/v1/publico/asistente', ['mensaje' => '¿Cuánto cuesta?', 'sesion' => 'visitante1'])->assertOk()->json('datos');
        $this->assertStringContainsString('plan', $datos['texto']);
        $this->assertStringContainsString('wa.me', $datos['acciones'][0]['ruta']);
        $this->assertSame('local', $datos['origen']);
    }

    public function test_el_asistente_del_panel_respeta_el_rol(): void
    {
        $this->actingAs($this->tecnico, 'sanctum');
        $this->assertStringContainsString('no tiene acceso', $this->postJson('/api/v1/asistente', ['mensaje' => 'facturas vencidas', 'sesion' => 's1'])->json('datos.texto'));
        $estado = $this->postJson('/api/v1/asistente', ['mensaje' => "estado del {$this->equipo->codigo}", 'sesion' => 's1'])->assertOk()->json('datos');
        $this->assertStringContainsString($this->equipo->codigo, $estado['texto']);

        $this->actingAs($this->administrador, 'sanctum');
        $pdf = $this->postJson('/api/v1/asistente', ['mensaje' => 'pdf de indicadores', 'sesion' => 's2'])->json('datos.acciones.0');
        $this->assertSame(['tipo' => 'pdf', 'ruta' => '/indicadores/servicio/pdf'], ['tipo' => $pdf['tipo'], 'ruta' => $pdf['ruta']]);
        $this->assertStringContainsString('Facturas', $this->postJson('/api/v1/asistente', ['mensaje' => 'hola', 'sesion' => 's2'])->json('datos.texto'));
    }

    public function test_con_n8n_activo_el_agente_usa_un_token_temporal_que_solo_sirve_para_las_herramientas(): void
    {
        config(['services.asistente' => array_merge(config('services.asistente'), ['n8n_activo' => true, 'url_panel' => 'https://n8n.test/webhook/sigma-panel', 'clave' => 'clave-n8n'])]);
        $token = null;
        Http::fake(['n8n.test/*' => function (SolicitudHttp $s) use (&$token) {
            $token = $s['herramientas']['token'];

            return Http::response(['respuesta' => 'Tiene 1 orden activa.']);
        }]);

        $this->actingAs($this->administrador, 'sanctum');
        $datos = $this->postJson('/api/v1/asistente', ['mensaje' => '¿qué órdenes tengo?', 'sesion' => 's3'])->assertOk()->json('datos');
        $this->assertSame(['texto' => 'Tiene 1 orden activa.', 'origen' => 'n8n'], ['texto' => $datos['texto'], 'origen' => $datos['origen']]);
        Http::assertSent(fn (SolicitudHttp $s) => $s->hasHeader('X-Sigma-Clave', 'clave-n8n') && $s['usuario']['rol'] === 'administrador' && array_key_exists('cartera_vencida', $s['acciones_disponibles']));
        $this->assertNotNull($token);
        $this->assertSame(0, PersonalAccessToken::where('name', 'asistente-n8n')->count(), 'el token temporal se borra al terminar');

        // Un token del asistente solo abre /asistente/herramienta.
        $temporal = $this->administrador->createToken('asistente-n8n', ['asistente'], now()->addMinutes(10))->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($temporal)->postJson('/api/v1/asistente/herramienta', ['accion' => 'pdf_indicadores'])->assertOk()->assertJsonPath('datos.acciones.0.ruta', '/indicadores/servicio/pdf');
        $this->app['auth']->forgetGuards();
        $this->withToken($temporal)->getJson('/api/v1/usuarios')->assertForbidden();

        // Un token normal no usa la ruta de herramientas.
        $normal = $this->administrador->createToken('panel')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($normal)->postJson('/api/v1/asistente/herramienta', ['accion' => 'pdf_indicadores'])->assertForbidden();

        // Si n8n falla, responde el motor local.
        config(['services.asistente.url_panel' => 'https://caido.test/webhook/sigma-panel']);
        Http::fake(['caido.test/*' => Http::response(null, 500)]);
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->administrador, 'sanctum');
        $this->assertSame('local', $this->postJson('/api/v1/asistente', ['mensaje' => 'emergencias', 'sesion' => 's4'])->json('datos.origen'));
    }
}
