<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Vencimiento absoluto de los tokens (config/sanctum.php → expiration), además del cierre por inactividad.
 */
class PruebasVencimientoTokensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolSeeder::class);
        $this->seed(PermisoSeeder::class);
    }

    private function iniciarSesion(): string
    {
        $usuario = Usuario::factory()->create([
            'password' => 'clave12345',
            'rol_id' => Rol::where('nombre', 'administrador')->value('id'),
            'activo' => true,
        ]);

        return $this->postJson('/api/v1/iniciar-sesion', [
            'correo' => $usuario->correo,
            'password' => 'clave12345',
        ])->assertOk()->json('token_acceso');
    }

    private function consultarPerfil(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/v1/perfil');
    }

    public function test_los_tokens_vencen_a_las_12_horas_por_defecto(): void
    {
        $this->assertSame(720, config('sanctum.expiration'));
    }

    public function test_un_token_usado_sin_parar_vence_a_las_12_horas(): void
    {
        $token = $this->iniciarSesion();

        // Se usa cada 9 minutos (nunca llega al cierre por inactividad) durante casi 12 horas.
        foreach (range(1, 79) as $uso) {
            $this->travel(9)->minutes();
            $this->consultarPerfil($token)->assertOk();
        }

        // 711 minutos después sigue sirviendo; a los 720 ya no, aunque se haya usado hace 9 minutos.
        $this->travel(9)->minutes();
        $this->consultarPerfil($token)->assertUnauthorized();
    }

    public function test_la_tarea_programada_borra_los_tokens_vencidos(): void
    {
        $this->iniciarSesion();
        $this->assertSame(1, PersonalAccessToken::count());

        $this->travel(720 + 25 * 60)->minutes();
        $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();

        $this->assertSame(0, PersonalAccessToken::count());
    }
}
