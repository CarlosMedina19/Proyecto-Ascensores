<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\MantenimientoProgramado;
use App\Models\Periodicidad;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** El técnico registra la visita solo con mantenimiento activo, emergencia abierta o fecha cercana. */
class PruebasVisitaTecnicoTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $administrador;

    private Usuario $tecnico;

    private Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:00:00')); // lunes
        Notification::fake();
        // El mismo sembrado que corre en cada despliegue (incluye los ítems de la planilla).
        config(['admin.correo' => 'admin@sigma.test', 'admin.password' => 'ClaveSegura12345']);
        $this->seed(DatabaseSeeder::class);
        $rol = fn ($nombre) => Rol::where('nombre', $nombre)->value('id');
        $this->administrador = Usuario::factory()->create(['rol_id' => $rol('administrador'), 'activo' => true]);
        $cliente = Cliente::factory()->create();
        $this->equipo = Equipo::factory()->create(['edificio_id' => Edificio::factory()->create(['cliente_id' => $cliente->id])->id, 'estado' => 'activo']);

        $this->actingAs($this->administrador, 'sanctum');
        $usuarioId = $this->postJson('/api/v1/tecnicos', [
            'nombre' => 'Andrés Rojas', 'correo' => 'andres@sigma.test', 'password' => 'Clave1234', 'tipo_documento' => 'CC', 'numero_documento' => '10203040',
            'especialidades' => [Especialidad::value('id')],
        ])->assertCreated()->json('datos.usuario_id');
        $this->tecnico = Usuario::findOrFail($usuarioId);
        // El plan arranca en 10 días; el primer mantenimiento queda más adelante.
        $this->postJson('/api/v1/asignaciones', [
            'equipo_id' => $this->equipo->id, 'periodicidad_id' => Periodicidad::where('meses', 1)->value('id'), 'fecha_inicio' => '2026-11-19',
        ])->assertCreated();
    }

    private function primerMantenimiento(): CarbonImmutable
    {
        return CarbonImmutable::parse(MantenimientoProgramado::where('equipo_id', $this->equipo->id)->orderBy('fecha_programada')->value('fecha_programada'));
    }

    private function visita(): array
    {
        return $this->getJson("/api/v1/equipos/{$this->equipo->id}")->assertOk()->json('datos.visita');
    }

    private function registrar()
    {
        return $this->postJson("/api/v1/equipos/{$this->equipo->id}/historial", ['evento' => 'mantenimiento', 'descripcion' => 'Visita de prueba']);
    }

    public function test_sin_mantenimiento_cercano_ni_emergencia_el_tecnico_no_registra_la_visita(): void
    {
        $this->actingAs($this->tecnico, 'sanctum');
        $visita = $this->visita();
        $this->assertFalse($visita['permitida']);
        $this->assertStringContainsString($this->primerMantenimiento()->format('d/m/Y'), $visita['motivo']);
        $this->registrar()->assertStatus(422);

        // Otros eventos del historial no tienen la restricción, ni otros roles.
        $this->postJson("/api/v1/equipos/{$this->equipo->id}/historial", ['evento' => 'incidente', 'descripcion' => 'Ruido'])->assertCreated();
        $this->actingAs($this->administrador, 'sanctum');
        $this->registrar()->assertCreated();
    }

    public function test_con_el_mantenimiento_a_dos_dias_puede_registrarla(): void
    {
        $this->travelTo($this->primerMantenimiento()->subDays(2)->setTime(8, 0));
        $this->actingAs($this->tecnico, 'sanctum');
        $this->assertTrue($this->visita()['permitida']);
        $this->registrar()->assertCreated();
    }

    public function test_con_una_emergencia_abierta_puede_registrarla(): void
    {
        $this->actingAs($this->tecnico, 'sanctum');
        $this->postJson('/api/v1/emergencias', ['equipo_id' => $this->equipo->id, 'tipo' => 'falla', 'descripcion' => 'No arranca'])->assertCreated();
        $visita = $this->visita();
        $this->assertTrue($visita['permitida']);
        $this->assertStringContainsString('Emergencia', $visita['motivo']);
        $this->registrar()->assertCreated();
    }

    public function test_con_un_mantenimiento_activo_puede_registrarla(): void
    {
        $this->actingAs($this->tecnico, 'sanctum');
        $this->postJson("/api/v1/qr/{$this->equipo->codigo_qr}/mantenimiento")->assertCreated();
        $visita = $this->visita();
        $this->assertTrue($visita['permitida']);
        $this->assertStringContainsString('Mantenimiento activo', $visita['motivo']);
    }

    public function test_el_sembrado_de_produccion_crea_la_planilla_sin_pisar_cambios(): void
    {
        $this->assertSame(collect(ChecklistSeeder::ITEMS)->flatten(1)->count(), ChecklistItem::count());
        $item = ChecklistItem::first();
        $item->update(['nombre' => 'Nombre ajustado', 'activo' => false]);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame('Nombre ajustado', $item->fresh()->nombre);
        $this->assertFalse($item->fresh()->activo);
    }
}
