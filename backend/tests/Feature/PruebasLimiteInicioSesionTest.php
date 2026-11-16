<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Límite de intentos de inicio de sesión por dirección IP (relleno de credenciales).
 */
class PruebasLimiteInicioSesionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolSeeder::class);
        $this->seed(PermisoSeeder::class);
    }

    private function intentar(string $correo, string $ip = '203.0.113.10', string $password = 'incorrecta')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/iniciar-sesion', ['correo' => $correo, 'password' => $password]);
    }

    public function test_una_ip_no_puede_probar_contrasenas_en_muchas_cuentas(): void
    {
        // 20 intentos, cada uno con un correo distinto: el límite por correo nunca se alcanza.
        foreach (range(1, 20) as $n) {
            $this->intentar("cuenta{$n}@ascensores.test")->assertUnauthorized();
        }

        $this->intentar('cuenta21@ascensores.test')
            ->assertTooManyRequests()
            ->assertJson(['mensaje' => 'Demasiados intentos de inicio de sesión. Espere un minuto.']);
    }

    public function test_el_limite_de_una_ip_no_bloquea_a_otras(): void
    {
        foreach (range(1, 20) as $n) {
            $this->intentar("cuenta{$n}@ascensores.test");
        }
        $this->intentar('otra@ascensores.test')->assertTooManyRequests();

        $usuario = Usuario::factory()->create([
            'password' => 'clave12345',
            'rol_id' => Rol::where('nombre', 'administrador')->value('id'),
            'activo' => true,
        ]);
        $this->intentar($usuario->correo, '198.51.100.7', 'clave12345')->assertOk();
    }

    public function test_el_limite_por_ip_se_libera_al_minuto(): void
    {
        foreach (range(1, 20) as $n) {
            $this->intentar("cuenta{$n}@ascensores.test");
        }
        $this->intentar('cuenta21@ascensores.test')->assertTooManyRequests();

        $this->travel(61)->seconds();
        $this->intentar('cuenta21@ascensores.test')->assertUnauthorized();
    }
}
