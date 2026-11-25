<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\ChecklistSeccion;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\PlanillaService;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Planilla técnica: el administrador desactiva una sección con un botón y la vuelve a activar. */
class PruebasSeccionesPlanillaTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $administrador;

    private Usuario $tecnico;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed([RolSeeder::class, PermisoSeeder::class, ConfiguracionSeeder::class, ChecklistSeeder::class]);
        $rol = fn ($nombre) => Rol::where('nombre', $nombre)->value('id');
        $this->administrador = Usuario::factory()->create(['rol_id' => $rol('administrador'), 'activo' => true]);
        $this->tecnico = Usuario::factory()->create(['rol_id' => $rol('tecnico'), 'activo' => true]);
    }

    private function claves(array $secciones): array
    {
        return array_column($secciones, 'clave');
    }

    public function test_una_seccion_desactivada_sale_de_las_planillas_y_vuelve_al_activarla(): void
    {
        $foso = ChecklistSeccion::where('clave', 'foso')->firstOrFail();
        $actividadesFoso = ChecklistItem::where('seccion', 'foso')->where('activo', true)->count();
        $this->assertGreaterThan(0, $actividadesFoso);
        $total = ChecklistItem::vigentes()->count();

        $this->actingAs($this->administrador, 'sanctum');
        $respuesta = $this->patchJson("/api/v1/planilla/secciones/{$foso->id}", ['habilitada' => false])->assertOk();
        $this->assertStringContainsString('desactivada', $respuesta->json('mensaje'));
        // El administrador la sigue viendo (atenuada) para poder activarla, con sus actividades.
        $desactivada = collect($respuesta->json('datos'))->firstWhere('clave', 'foso');
        $this->assertFalse($desactivada['habilitada']);
        $this->assertCount($actividadesFoso, $desactivada['items']);
        $this->assertContains('foso', $this->claves($this->getJson('/api/v1/planilla/plantilla?todas=1')->json('datos')));

        // Las visitas y las órdenes ya no la piden.
        $this->assertNotContains('foso', $this->claves($this->getJson('/api/v1/planilla/plantilla')->json('datos')));
        $this->assertSame($total - $actividadesFoso, ChecklistItem::vigentes()->count());
        $this->assertSame($total - $actividadesFoso, app(PlanillaService::class)->faltantes(null));
        $this->assertNotContains('foso', app(PlanillaService::class)->catalogo()->pluck('seccion')->unique()->all());

        $this->actingAs($this->tecnico, 'sanctum');
        $this->assertNotContains('foso', $this->claves($this->getJson('/api/v1/planilla/plantilla?todas=1')->json('datos')));

        $this->actingAs($this->administrador, 'sanctum');
        $this->patchJson("/api/v1/planilla/secciones/{$foso->id}", ['habilitada' => true])->assertOk()->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'activada'));
        $this->assertSame($total, ChecklistItem::vigentes()->count());
        $this->assertContains('foso', $this->claves($this->getJson('/api/v1/planilla/plantilla')->json('datos')));
    }

    public function test_siempre_queda_una_seccion_activa_y_solo_el_administrador_la_cambia(): void
    {
        $secciones = ChecklistSeccion::enUso()->orderBy('orden')->get();
        $ultima = $secciones->pop();

        $this->actingAs($this->tecnico, 'sanctum');
        $this->patchJson("/api/v1/planilla/secciones/{$ultima->id}", ['habilitada' => false])->assertForbidden();

        $this->actingAs($this->administrador, 'sanctum');
        foreach ($secciones as $seccion) {
            $this->patchJson("/api/v1/planilla/secciones/{$seccion->id}", ['habilitada' => false])->assertOk();
        }
        $this->patchJson("/api/v1/planilla/secciones/{$ultima->id}", ['habilitada' => false])->assertStatus(422);
        $this->deleteJson("/api/v1/planilla/secciones/{$ultima->id}")->assertStatus(422);
        $this->assertTrue($ultima->fresh()->habilitada);

        // Restablecer la planilla original vuelve a activar todas.
        $this->postJson('/api/v1/planilla/restablecer')->assertOk();
        $this->assertSame(0, ChecklistSeccion::where('activo', true)->where('habilitada', false)->count());
    }
}
