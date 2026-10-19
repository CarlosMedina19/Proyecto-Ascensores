<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\InformeOrden;
use App\Models\OrdenTrabajo;
use App\Models\PlanillaResultado;
use App\Models\Rol;
use App\Models\Tecnico;
use App\Models\Usuario;
use App\Notifications\ChecklistNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\EspecialidadSeeder;
use Database\Seeders\PeriodicidadSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

/**
 * Pruebas de la fase 4: planilla de mantenimiento, resultados, correctivo derivado, firmas e informe PDF
 * (RF-030 a RF-039). La fecha actual se fija en el lunes 5 de enero de 2026 a las 10:00.
 */
class PruebasFaseCuatroTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected Usuario $coordinador;

    protected Tecnico $tecnico;

    protected Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-01-05 10:00:00'));
        Notification::fake();
        Storage::fake('local');
        $this->seed([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class, EspecialidadSeeder::class, ChecklistSeeder::class]);

        $this->coordinador = $this->usuarioConRol('coordinador');
        $usuario = $this->usuarioConRol('tecnico', ['nombre' => 'Andrés Rojas']);
        $this->tecnico = Tecnico::create(['usuario_id' => $usuario->id, 'tipo_documento' => 'CC', 'numero_documento' => '1110450002', 'disponibilidad' => 'disponible']);
        $this->tecnico->especialidades()->attach(Especialidad::first());
        $edificio = Edificio::factory()->create(['cliente_id' => Cliente::factory()->create()->id]);
        $this->equipo = Equipo::factory()->create(['edificio_id' => $edificio->id]);
    }

    private function usuarioConRol(string $rol, array $datos = []): Usuario
    {
        return Usuario::factory()->create($datos + ['rol_id' => Rol::where('nombre', $rol)->value('id'), 'activo' => true]);
    }

    /** Orden asignada al técnico; con $enProceso el técnico ya la inició. */
    private function crearOrden(string $servicio = 'preventivo', bool $enProceso = true, string $hora = '10:00'): OrdenTrabajo
    {
        $id = $this->actingAs($this->coordinador, 'sanctum')->postJson('/api/v1/ordenes', [
            'servicio' => $servicio,
            'cliente_id' => $this->equipo->edificio->cliente_id,
            'edificio_id' => $this->equipo->edificio_id,
            'equipo_id' => $this->equipo->id,
            'fecha' => '2026-01-05',
            'hora' => $hora,
            'tecnicos' => [$this->tecnico->id],
        ])->assertCreated()->json('datos.id');

        if ($enProceso) {
            $this->actingAs($this->tecnico->usuario, 'sanctum');
            foreach (['en_camino', 'en_proceso'] as $estado) {
                $this->patchJson("/api/v1/ordenes/{$id}/estado", ['estado' => $estado])->assertOk();
            }
        }

        return OrdenTrabajo::findOrFail($id);
    }

    private function todosEnBueno(array $excepto = []): array
    {
        return ChecklistItem::all()
            ->reject(fn ($i) => array_key_exists($i->codigo, $excepto))
            ->map(fn ($i) => ['item_id' => $i->id, 'resultado' => 'INS', 'estado' => 'B'])
            ->values()
            ->all();
    }

    private function item(string $codigo): ChecklistItem
    {
        return ChecklistItem::where('codigo', $codigo)->firstOrFail();
    }

    private function firmar(OrdenTrabajo $orden): void
    {
        foreach (['tecnico' => 'Andrés Rojas', 'cliente' => 'Laura Gómez'] as $tipo => $nombre) {
            $this->postJson("/api/v1/ordenes/{$orden->id}/firmas", ['tipo' => $tipo, 'nombre' => $nombre, 'imagen' => 'data:image/png;base64,'.self::PNG])
                ->assertCreated();
        }
    }

    // ---------------------------------------------------------------- RF-030 a RF-037

    public function test_el_catalogo_tiene_las_seis_secciones(): void
    {
        $respuesta = $this->actingAs($this->coordinador, 'sanctum')->getJson('/api/v1/checklist/items')->assertOk();

        $secciones = collect($respuesta->json('datos'))->groupBy('seccion')->map->count();
        $this->assertSame(
            ['cuarto_maquinas' => 12, 'cabina' => 11, 'contrapeso' => 5, 'puertas' => 9, 'hueco' => 7, 'foso' => 4],
            $secciones->all()
        );
        $this->assertTrue(collect($respuesta->json('datos'))->firstWhere('codigo', 'FO-02')['critico']);
    }

    public function test_la_planilla_solo_se_diligencia_en_un_preventivo_en_proceso_por_su_tecnico(): void
    {
        $fila = ['resultados' => [['item_id' => $this->item('CM-01')->id, 'resultado' => 'INS', 'estado' => 'B']]];

        $correctivo = $this->crearOrden('correctivo');
        $this->putJson("/api/v1/ordenes/{$correctivo->id}/planilla", $fila)
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', 'La planilla de mantenimiento solo se diligencia en órdenes preventivas.');

        $asignada = $this->crearOrden('preventivo', false, '13:00');
        $this->actingAs($this->tecnico->usuario, 'sanctum')
            ->putJson("/api/v1/ordenes/{$asignada->id}/planilla", $fila)
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', 'La planilla se diligencia con la orden en proceso.');

        $orden = $this->crearOrden('preventivo', true, '16:00');
        $this->actingAs($this->coordinador, 'sanctum')->putJson("/api/v1/ordenes/{$orden->id}/planilla", $fila)->assertForbidden();

        $this->actingAs($this->tecnico->usuario, 'sanctum')
            ->putJson("/api/v1/ordenes/{$orden->id}/planilla", $fila)
            ->assertOk()
            ->assertJsonPath('faltantes', 47);
    }

    public function test_cada_item_tiene_resultado_y_estado_y_r_o_m_exigen_observacion(): void
    {
        $orden = $this->crearOrden();
        $item = $this->item('CA-02')->id;

        $this->putJson("/api/v1/ordenes/{$orden->id}/planilla", ['resultados' => [['item_id' => $item, 'resultado' => 'OK', 'estado' => 'X']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['resultados.0.resultado', 'resultados.0.estado'], 'errores');

        $this->putJson("/api/v1/ordenes/{$orden->id}/planilla", ['resultados' => [['item_id' => $item, 'resultado' => 'EST', 'estado' => 'R']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['resultados.0.observacion' => 'Un ítem en estado Regular o Malo exige una observación.'], 'errores');

        $this->putJson("/api/v1/ordenes/{$orden->id}/planilla", [
            'resultados' => [['item_id' => $item, 'resultado' => 'EST', 'estado' => 'R', 'observacion' => 'Dos bombillos fundidos']],
            'recomendaciones' => 'Cambiar a iluminación LED.',
        ])->assertOk()
            ->assertJsonPath('datos.resultados.0.estado', 'R')
            ->assertJsonPath('datos.recomendaciones', 'Cambiar a iluminación LED.');

        // Se puede corregir: un ítem tiene un solo resultado.
        $this->putJson("/api/v1/ordenes/{$orden->id}/planilla", ['resultados' => [['item_id' => $item, 'resultado' => 'NA', 'estado' => 'B']]])->assertOk();
        $this->assertSame(1, PlanillaResultado::where('item_id', $item)->count());
        $this->assertSame('NA', PlanillaResultado::where('item_id', $item)->value('resultado'));
    }

    public function test_un_item_critico_en_malo_avisa_de_inmediato_y_una_sola_vez(): void
    {
        $orden = $this->crearOrden();
        $parada = ['item_id' => $this->item('FO-02')->id, 'resultado' => 'INS', 'estado' => 'M', 'observacion' => 'No detiene el ascensor'];

        $this->putJson("/api/v1/ordenes/{$orden->id}/planilla", ['resultados' => [$parada]])->assertOk();
        Notification::assertSentTo($this->coordinador, ChecklistNotification::class, fn ($n) => $n->inmediato && str_contains($n->mensaje(), 'Parada de emergencia'));

        // Un aviso sale por dos canales (aplicación y correo). Guardar de nuevo no lo repite.
        Notification::assertSentToTimes($this->coordinador, ChecklistNotification::class, 2);
        $this->putJson("/api/v1/ordenes/{$orden->id}/planilla", ['resultados' => [$parada]])->assertOk();
        Notification::assertSentToTimes($this->coordinador, ChecklistNotification::class, 2);

        // Un ítem no crítico en M no avisa de inmediato.
        $this->putJson("/api/v1/ordenes/{$orden->id}/planilla", ['resultados' => [['item_id' => $this->item('CA-03')->id, 'resultado' => 'INS', 'estado' => 'M', 'observacion' => 'Espejo roto']]])->assertOk();
        Notification::assertSentToTimes($this->coordinador, ChecklistNotification::class, 2);
    }

    // ---------------------------------------------------------------- RF-025, RF-030 y RF-039

    public function test_finalizar_exige_planilla_completa_y_firmas_y_emite_el_informe(): void
    {
        $orden = $this->crearOrden();
        $finalizar = ['estado' => 'finalizada', 'informe' => 'Mantenimiento preventivo completo.'];

        $this->patchJson("/api/v1/ordenes/{$orden->id}/estado", $finalizar)
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', 'Para finalizar la orden falta 48 ítems de la planilla y la firma del técnico y la firma de quien recibe.');

        $this->putJson("/api/v1/ordenes/{$orden->id}/planilla", [
            'resultados' => [...$this->todosEnBueno(['CA-03' => true]), ['item_id' => $this->item('CA-03')->id, 'resultado' => 'INS', 'estado' => 'M', 'observacion' => 'Espejo roto']],
        ])->assertOk()->assertJsonPath('faltantes', 0);
        $this->firmar($orden);
        $this->post("/api/v1/ordenes/{$orden->id}/planilla/fotos", ['foto' => UploadedFile::fake()->createWithContent('cabina.png', base64_decode(self::PNG)), 'item_id' => $this->item('CA-03')->id], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->patchJson("/api/v1/ordenes/{$orden->id}/estado", $finalizar)
            ->assertOk()
            ->assertJsonPath('datos.estado', 'finalizada')
            ->assertJsonPath('datos.planilla.completa', true)
            ->assertJsonPath('datos.planilla.malos', 1)
            ->assertJsonPath('datos.informes.0.version', 1);

        // Los ítems en M no críticos se avisan al finalizar.
        Notification::assertSentTo($this->coordinador, ChecklistNotification::class, fn ($n) => ! $n->inmediato && str_contains($n->mensaje(), 'Espejo roto'));

        $informe = InformeOrden::where('orden_id', $orden->id)->firstOrFail();
        Storage::disk('local')->assertExists($informe->ruta);
        $this->get("/api/v1/ordenes/{$orden->id}/informes/{$informe->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($informe->ruta));
        $this->assertNotNull($orden->fresh()->planilla->completada_en);

        // El esquema del ascensor muestra el resultado por sección de la última planilla.
        $this->actingAs($this->coordinador, 'sanctum')
            ->getJson("/api/v1/equipos/{$this->equipo->id}")
            ->assertJsonPath('datos.ultima_revision.secciones.cabina', 'M')
            ->assertJsonPath('datos.ultima_revision.secciones.foso', 'B');
    }

    public function test_un_informe_emitido_no_cambia_y_las_correcciones_son_versiones_nuevas(): void
    {
        $orden = $this->crearOrden();
        $this->putJson("/api/v1/ordenes/{$orden->id}/planilla", ['resultados' => $this->todosEnBueno()])->assertOk();
        $this->firmar($orden);
        $this->patchJson("/api/v1/ordenes/{$orden->id}/estado", ['estado' => 'finalizada', 'informe' => 'Mantenimiento preventivo completo.'])->assertOk();
        $primera = InformeOrden::where('orden_id', $orden->id)->firstOrFail();

        $this->postJson("/api/v1/ordenes/{$orden->id}/informes", ['motivo' => 'Corrección del nombre del firmante'])->assertForbidden();

        $this->actingAs($this->coordinador, 'sanctum')
            ->postJson("/api/v1/ordenes/{$orden->id}/informes", ['motivo' => 'Corrección del nombre del firmante'])
            ->assertCreated()
            ->assertJsonPath('datos.version', 2);

        $this->getJson("/api/v1/ordenes/{$orden->id}/informes")->assertOk()->assertJsonCount(2, 'datos')->assertJsonPath('datos.0.version', 2);
        $this->assertSame($primera->hash, $primera->fresh()->hash);
        Storage::disk('local')->assertExists($primera->ruta);

        $this->expectException(LogicException::class);
        $primera->update(['motivo' => 'cambiado']);
    }

    public function test_las_firmas_son_imagenes_png_y_se_reemplazan(): void
    {
        $orden = $this->crearOrden('correctivo');

        $this->postJson("/api/v1/ordenes/{$orden->id}/firmas", ['tipo' => 'cliente', 'nombre' => 'Laura Gómez', 'imagen' => base64_encode('no es una imagen')])
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', 'La firma debe ser una imagen PNG.');

        $this->firmar($orden);
        $this->postJson("/api/v1/ordenes/{$orden->id}/firmas", ['tipo' => 'cliente', 'nombre' => 'Pedro Pérez', 'cargo' => 'Administrador', 'imagen' => self::PNG])->assertCreated();

        $this->assertSame(2, $orden->firmas()->count());
        $this->assertSame('Pedro Pérez', $orden->firmas()->where('tipo', 'cliente')->value('nombre'));

        // Un correctivo no lleva planilla: con las firmas y el informe se finaliza.
        $this->patchJson("/api/v1/ordenes/{$orden->id}/estado", ['estado' => 'finalizada', 'informe' => 'Se ajustó la nivelación de la cabina.'])
            ->assertOk()
            ->assertJsonPath('datos.lleva_planilla', false);
    }

    // ---------------------------------------------------------------- RF-038

    public function test_el_correctivo_derivado_queda_vinculado_al_item_y_no_se_duplica(): void
    {
        $orden = $this->crearOrden();
        $this->putJson("/api/v1/ordenes/{$orden->id}/planilla", ['resultados' => [
            ['item_id' => $this->item('FO-04')->id, 'resultado' => 'INS', 'estado' => 'M', 'observacion' => 'Amortiguador con fuga de aceite'],
            ['item_id' => $this->item('FO-01')->id, 'resultado' => 'INS', 'estado' => 'B'],
        ]])->assertOk();
        $malo = PlanillaResultado::whereHas('item', fn ($i) => $i->where('codigo', 'FO-04'))->firstOrFail();
        $bueno = PlanillaResultado::whereHas('item', fn ($i) => $i->where('codigo', 'FO-01'))->firstOrFail();

        $this->postJson("/api/v1/ordenes/{$orden->id}/planilla/resultados/{$bueno->id}/correctivo")
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', 'Solo los ítems en estado Regular o Malo generan una orden correctiva.');

        // El técnico asignado lo crea; queda programado para el siguiente día hábil.
        $respuesta = $this->postJson("/api/v1/ordenes/{$orden->id}/planilla/resultados/{$malo->id}/correctivo")
            ->assertCreated()
            ->assertJsonPath('datos.servicio', 'correctivo')
            ->assertJsonPath('datos.prioridad', 'alta')
            ->assertJsonPath('datos.estado', 'programada')
            ->assertJsonPath('datos.fecha', '2026-01-06');
        $this->assertStringContainsString('Amortiguadores en estado Malo', $respuesta->json('datos.descripcion'));

        $correctivo = OrdenTrabajo::findOrFail($respuesta->json('datos.id'));
        $this->assertSame($orden->id, $correctivo->orden_origen_id);
        $this->assertSame($malo->id, $correctivo->resultado_origen_id);

        $this->postJson("/api/v1/ordenes/{$orden->id}/planilla/resultados/{$malo->id}/correctivo")
            ->assertUnprocessable()
            ->assertJsonPath('mensaje', "El ítem ya tiene el correctivo abierto {$correctivo->numero}.");

        $this->actingAs($this->coordinador, 'sanctum')
            ->getJson("/api/v1/ordenes/{$orden->id}")
            ->assertJsonPath('datos.correctivos_derivados.0.numero', $correctivo->numero);
        $this->getJson("/api/v1/ordenes/{$correctivo->id}")->assertJsonPath('datos.orden_origen.item', 'Amortiguadores');
    }
}
