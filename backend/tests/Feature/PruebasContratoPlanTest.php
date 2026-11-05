<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\Periodicidad;
use App\Models\PlanMantenimiento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\PlanService;
use Carbon\CarbonImmutable;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PeriodicidadSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Planes de alquiler del software ajustados en el contrato, PDF del contrato y mantenimiento sin plan. */
class PruebasContratoPlanTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $administrador;

    private PlanMantenimiento $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-01-05 10:00:00'));
        Notification::fake();
        $this->seed([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class]);
        $this->administrador = Usuario::factory()->create(['rol_id' => Rol::where('nombre', 'administrador')->value('id'), 'activo' => true]);
        $this->plan = app(PlanService::class)->crear([
            'nombre' => 'SIGMA Profesional',
            'servicios_incluidos' => ['Panel web', 'App para técnicos', 'Soporte por correo'],
            'activo' => true,
            'precios' => [
                ['periodicidad_id' => $this->periodicidad(1), 'precio' => 300000],
                ['periodicidad_id' => $this->periodicidad(3), 'precio' => 850000],
                ['periodicidad_id' => $this->periodicidad(6), 'precio' => 1600000],
            ],
        ]);
        $this->actingAs($this->administrador, 'sanctum');
    }

    private function periodicidad(int $meses): int
    {
        return Periodicidad::where('meses', $meses)->value('id');
    }

    private function equipo(Cliente $cliente): Equipo
    {
        return Equipo::factory()->create(['edificio_id' => Edificio::factory()->create(['cliente_id' => $cliente->id])->id]);
    }

    public function test_el_contrato_toma_el_plan_del_catalogo_y_permite_ajustarlo(): void
    {
        $cliente = Cliente::factory()->create();
        $equipo = $this->equipo($cliente);

        // Sin precio ni valor: precio del catálogo trimestral × 4 trimestres del año; servicios del plan.
        $contrato = $this->postJson('/api/v1/contratos', [
            'cliente_id' => $cliente->id, 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31',
            'plan_id' => $this->plan->id, 'periodicidad_id' => $this->periodicidad(3), 'equipos' => [$equipo->id],
        ])->assertCreated()->json('datos');
        $this->assertEquals(850000, $contrato['precio_periodo']);
        $this->assertEquals(3400000, $contrato['valor']);
        $this->assertSame(4, $contrato['periodos']);
        $this->assertSame(['Panel web', 'App para técnicos', 'Soporte por correo'], $contrato['servicios_pactados']);

        // Ajuste: precio pactado, servicios propios y periodicidad semestral.
        $ajustado = $this->patchJson("/api/v1/contratos/{$contrato['id']}", [
            'periodicidad_id' => $this->periodicidad(6), 'precio_periodo' => 1500000,
            'servicios_pactados' => ['Panel web', 'Capacitación inicial'], 'valor' => 3000000,
        ])->assertOk()->json('datos');
        $this->assertEquals(1500000, $ajustado['precio_periodo']);
        $this->assertEquals(1600000, $ajustado['precio_catalogo']);
        $this->assertSame(2, $ajustado['periodos']);
        $this->assertSame(['Panel web', 'Capacitación inicial'], $ajustado['servicios_pactados']);

        // Una periodicidad sin precio en el plan exige el precio pactado.
        $this->postJson('/api/v1/contratos', [
            'cliente_id' => $cliente->id, 'fecha_inicio' => '2027-01-01', 'fecha_fin' => '2027-12-31',
            'plan_id' => $this->plan->id, 'periodicidad_id' => $this->periodicidad(2), 'equipos' => [$equipo->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['precio_periodo'], 'errores');

        // El PDF del contrato se descarga con su número.
        $pdf = $this->get("/api/v1/contratos/{$contrato['id']}/pdf")->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString("SIGMA_Contrato_{$contrato['numero']}.pdf", $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_el_programa_de_mantenimiento_del_ascensor_no_necesita_plan(): void
    {
        $equipo = $this->equipo(Cliente::factory()->create());
        $asignacion = $this->postJson('/api/v1/asignaciones', [
            'equipo_id' => $equipo->id, 'periodicidad_id' => $this->periodicidad(1), 'fecha_inicio' => '2026-01-10',
        ])->assertCreated()->json('datos');
        $this->assertNull($asignacion['plan_id']);
        $this->assertEquals(0, $asignacion['precio']);
        $this->assertGreaterThan(0, $equipo->mantenimientos()->count());

        // Cambiar la frecuencia sin plan conserva el valor por visita.
        $this->patchJson("/api/v1/asignaciones/{$asignacion['id']}/plan", ['periodicidad_id' => $this->periodicidad(2), 'motivo' => 'El cliente pidió visitas bimestrales'])
            ->assertOk()->assertJsonPath('datos.periodicidad.meses', 2);
        $this->patchJson("/api/v1/asignaciones/{$asignacion['id']}/finalizar", ['motivo' => 'Fin del servicio'])->assertOk();
    }

    public function test_el_calendario_agrega_una_visita_adicional_que_no_se_pierde_al_reprogramar(): void
    {
        $equipo = $this->equipo(Cliente::factory()->create());
        $this->postJson('/api/v1/mantenimientos', ['equipo_id' => $equipo->id, 'fecha' => '2026-01-14'])
            ->assertStatus(422)->assertJsonPath('mensaje', "El ascensor {$equipo->codigo} no tiene programa de mantenimiento. Prográmelo desde su hoja de vida.");

        $asignacion = $this->postJson('/api/v1/asignaciones', [
            'equipo_id' => $equipo->id, 'periodicidad_id' => $this->periodicidad(1), 'fecha_inicio' => '2026-01-10',
        ])->assertCreated()->json('datos');

        $this->postJson('/api/v1/mantenimientos', ['equipo_id' => $equipo->id, 'fecha' => '2026-01-11'])->assertStatus(422); // domingo
        $this->postJson('/api/v1/mantenimientos', ['equipo_id' => $equipo->id, 'fecha' => '2026-01-02'])->assertStatus(422); // pasada
        $visita = $this->postJson('/api/v1/mantenimientos', ['equipo_id' => $equipo->id, 'fecha' => '2026-01-14', 'hora' => '10:30', 'motivo' => 'Revisión pedida por la administración'])
            ->assertCreated()->assertJsonPath('datos.adicional', true)->assertJsonPath('datos.hora', '10:30')->json('datos');
        $this->postJson('/api/v1/mantenimientos', ['equipo_id' => $equipo->id, 'fecha' => '2026-01-14'])->assertStatus(422); // ya tiene visita ese día

        // Cambiar la frecuencia vuelve a calcular las visitas, pero la adicional se conserva.
        $this->patchJson("/api/v1/asignaciones/{$asignacion['id']}/plan", ['periodicidad_id' => $this->periodicidad(2), 'motivo' => 'Visitas bimestrales'])->assertOk();
        $this->assertSame('programado', $equipo->mantenimientos()->find($visita['id'])->estado);

        // Reagendar la visita arrastrándola a otro día.
        $this->patchJson("/api/v1/mantenimientos/{$visita['id']}/reprogramar", ['fecha' => '2026-01-16', 'hora' => '14:00', 'motivo' => 'Reagendado desde el calendario'])
            ->assertOk()->assertJsonPath('datos.fecha_programada', '2026-01-16');

        $tecnico = Usuario::factory()->create(['rol_id' => Rol::where('nombre', 'tecnico')->value('id'), 'activo' => true]);
        $this->actingAs($tecnico, 'sanctum')->postJson('/api/v1/mantenimientos', ['equipo_id' => $equipo->id, 'fecha' => '2026-01-20'])->assertForbidden();
    }
}
