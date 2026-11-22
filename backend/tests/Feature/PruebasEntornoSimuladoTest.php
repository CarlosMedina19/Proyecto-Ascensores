<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Emergencia;
use App\Models\Equipo;
use App\Models\Factura;
use App\Models\MantenimientoProgramado;
use App\Models\OrdenTrabajo;
use App\Models\Repuesto;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\EntornoSimuladoSeeder;
use Database\Seeders\EspecialidadSeeder;
use Database\Seeders\PeriodicidadSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Entorno simulado: un año de operación inventada, coherente y sin avisos a nadie. */
class PruebasEntornoSimuladoTest extends TestCase
{
    use RefreshDatabase;

    public function test_carga_un_anio_de_operacion_coherente_una_sola_vez(): void
    {
        Notification::fake();
        $this->seed([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class, EspecialidadSeeder::class, ChecklistSeeder::class]);
        $administrador = Usuario::factory()->create(['rol_id' => Rol::where('nombre', 'administrador')->value('id')]);

        $this->seed(EntornoSimuladoSeeder::class);

        $this->assertSame(14, Cliente::where('observaciones', EntornoSimuladoSeeder::MARCA)->count());
        $this->assertSame(36, Equipo::where('codigo', 'like', 'ASC-2__')->count());
        $this->assertSame(6, Usuario::where('correo', 'like', '%@sigma-mantenimiento.co')->whereNotNull('telefono')->count());

        // Historia de 12 meses: preventivos ejecutados con su orden, correctivos y emergencias resueltas.
        $preventivas = OrdenTrabajo::where('servicio', 'preventivo')->where('estado', 'finalizada');
        $this->assertGreaterThan(150, (clone $preventivas)->count());
        $this->assertSame((clone $preventivas)->count(), MantenimientoProgramado::where('estado', 'ejecutado')->count());
        $this->assertGreaterThanOrEqual(10, (clone $preventivas)->pluck('fecha')->map(fn ($fecha) => $fecha->format('Y-m'))->unique()->count());
        $this->assertGreaterThan(20, OrdenTrabajo::where('servicio', 'correctivo')->count());
        $this->assertGreaterThan(15, Emergencia::where('estado', 'resuelta')->count());
        $this->assertTrue(Emergencia::whereIn('estado', ['reportada', 'asignada'])->exists(), 'hay emergencias abiertas hoy');
        $this->assertTrue(OrdenTrabajo::where('estado', 'en_proceso')->whereDate('fecha', today())->exists(), 'hay trabajo en curso hoy');

        // Números consecutivos y sin repetir.
        $this->assertSame(OrdenTrabajo::count(), OrdenTrabajo::distinct()->count('numero'));
        $this->assertSame(Emergencia::count(), Emergencia::distinct()->count('numero'));

        // Cartera: casi todo pagado, algo vencido; el inventario nunca queda negativo.
        $this->assertTrue(Factura::where('estado', 'pagada')->count() > Factura::where('saldo', '>', 0)->whereDate('fecha_vencimiento', '<', today())->count());
        $this->assertTrue(Factura::where('saldo', '>', 0)->whereDate('fecha_vencimiento', '<', today())->exists());
        $this->assertSame(0, Repuesto::where('stock', '<', 0)->count());

        // No avisa a nadie mientras carga.
        Notification::assertNothingSent();

        // Los indicadores del panel ya tienen datos.
        $this->actingAs($administrador, 'sanctum');
        $indicadores = $this->getJson('/api/v1/indicadores/servicio?desde='.now()->subDays(89)->toDateString().'&hasta='.now()->toDateString())->assertOk()->json('datos');
        $this->assertGreaterThan(0, $indicadores['cumplimiento_plan']['programados']);
        $this->assertGreaterThan(0, $indicadores['respuesta']['atendidas']);

        // Una segunda ejecución no duplica nada.
        $ordenes = OrdenTrabajo::count();
        $this->seed(EntornoSimuladoSeeder::class);
        $this->assertSame($ordenes, OrdenTrabajo::count());
        $this->assertSame(14, Cliente::where('observaciones', EntornoSimuladoSeeder::MARCA)->count());
    }
}
