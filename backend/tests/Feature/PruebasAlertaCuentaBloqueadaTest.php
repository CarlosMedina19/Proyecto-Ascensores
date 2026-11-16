<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\AlertaSeguridadNotification;
use App\Services\NotificacionService;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Cuando una cuenta se bloquea por intentos fallidos se avisa a los administradores y al dueño de la cuenta.
 */
class PruebasAlertaCuentaBloqueadaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seed(RolSeeder::class);
        $this->seed(PermisoSeeder::class);
    }

    private function crearUsuario(string $rol, array $datos = []): Usuario
    {
        return Usuario::factory()->create($datos + [
            'password' => 'clave12345',
            'rol_id' => Rol::where('nombre', $rol)->value('id'),
            'activo' => true,
        ]);
    }

    private function fallar(Usuario $usuario, int $veces): void
    {
        foreach (range(1, $veces) as $n) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
                ->postJson('/api/v1/iniciar-sesion', ['correo' => $usuario->correo, 'password' => 'incorrecta']);
        }
    }

    public function test_al_bloquearse_una_cuenta_se_avisa_a_los_administradores_y_al_dueno(): void
    {
        $administrador = $this->crearUsuario('administrador');
        $adminInactivo = $this->crearUsuario('administrador', ['activo' => false]);
        $coordinador = $this->crearUsuario('coordinador');
        $tecnico = $this->crearUsuario('tecnico');

        $this->fallar($tecnico, 5);

        $this->assertNotNull($tecnico->fresh()->bloqueado_hasta);
        foreach ([$administrador, $tecnico] as $destinatario) {
            Notification::assertSentTo($destinatario, AlertaSeguridadNotification::class, function ($aviso) use ($tecnico) {
                return $aviso->cuenta->is($tecnico)
                    && str_contains($aviso->mensaje(), $tecnico->correo)
                    && str_contains($aviso->mensaje(), '203.0.113.50');
            });
        }
        Notification::assertNotSentTo($adminInactivo, AlertaSeguridadNotification::class);
        Notification::assertNotSentTo($coordinador, AlertaSeguridadNotification::class);
    }

    public function test_los_intentos_fallidos_sin_bloqueo_no_generan_aviso(): void
    {
        $this->crearUsuario('administrador');
        $tecnico = $this->crearUsuario('tecnico');

        $this->fallar($tecnico, 4);

        $this->assertNull($tecnico->fresh()->bloqueado_hasta);
        Notification::assertNothingSent();
    }

    public function test_el_administrador_bloqueado_no_recibe_el_aviso_duplicado(): void
    {
        $administrador = $this->crearUsuario('administrador');

        $this->fallar($administrador, 5);

        // Es administrador y dueño de la cuenta a la vez: un aviso por canal (campana y/o correo), no dos.
        $canales = count(app(NotificacionService::class)->canales());
        $this->assertGreaterThan(0, $canales);
        Notification::assertSentToTimes($administrador, AlertaSeguridadNotification::class, $canales);
    }
}
