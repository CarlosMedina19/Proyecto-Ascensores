<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\RestablecerContrasenaNotification;
use App\Notifications\VerificarCorreoNotification;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\TestCase;

/**
 * Reglas de seguridad de RF-001, RF-003, RF-004 y RNF-02.
 */
class PruebasSeguridadTest extends TestCase
{
    use RefreshDatabase;

    protected Rol $rolAdministrador;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seed(RolSeeder::class);
        $this->seed(PermisoSeeder::class);
        $this->rolAdministrador = Rol::where('nombre', 'administrador')->firstOrFail();
    }

    private function crearUsuario(string $rol = 'administrador', array $datos = []): Usuario
    {
        return Usuario::factory()->create($datos + [
            'password' => 'clave12345',
            'rol_id' => Rol::where('nombre', $rol)->value('id'),
            'activo' => true,
        ]);
    }

    private function iniciarSesion(Usuario $usuario, string $password = 'clave12345'): string
    {
        return $this->postJson('/api/v1/iniciar-sesion', [
            'correo' => $usuario->correo,
            'password' => $password,
        ])->assertOk()->json('token_acceso');
    }

    /**
     * Los guards de autenticación se reutilizan entre solicitudes de una misma prueba.
     */
    private function getConToken(string $token, string $ruta)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson($ruta);
    }

    public function test_inicio_de_sesion_tiene_limite_de_intentos_por_minuto(): void
    {
        foreach (range(1, 5) as $intento) {
            $this->postJson('/api/v1/iniciar-sesion', [
                'correo' => 'limite@ascensores.test',
                'password' => 'incorrecta',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/iniciar-sesion', [
            'correo' => 'limite@ascensores.test',
            'password' => 'incorrecta',
        ])->assertTooManyRequests()->assertJsonStructure(['mensaje']);
    }

    public function test_cuenta_se_bloquea_15_minutos_tras_5_intentos_fallidos(): void
    {
        $usuario = $this->crearUsuario();

        foreach (range(1, 5) as $intento) {
            $this->postJson('/api/v1/iniciar-sesion', [
                'correo' => $usuario->correo,
                'password' => 'incorrecta',
            ])->assertUnauthorized();
        }

        $this->assertNotNull($usuario->fresh()->bloqueado_hasta);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'cuenta_bloqueada', 'modelo_id' => $usuario->id]);

        // Pasado el límite por minuto, la cuenta sigue bloqueada aun con la contraseña correcta.
        $this->travel(2)->minutes();
        $this->postJson('/api/v1/iniciar-sesion', [
            'correo' => $usuario->correo,
            'password' => 'clave12345',
        ])->assertStatus(423);

        $this->travel(14)->minutes();
        $this->iniciarSesion($usuario);
        $this->assertSame(0, $usuario->fresh()->intentos_fallidos);
    }

    public function test_sesion_expira_tras_10_minutos_de_inactividad(): void
    {
        $token = $this->iniciarSesion($this->crearUsuario());

        $this->getConToken($token, '/api/v1/perfil')->assertOk();

        $this->travel(9)->minutes();
        $this->getConToken($token, '/api/v1/perfil')->assertOk();

        $this->travel(11)->minutes();
        $this->getConToken($token, '/api/v1/perfil')
            ->assertUnauthorized()
            ->assertJsonStructure(['mensaje']);
    }

    public function test_cerrar_sesion_invalida_el_token(): void
    {
        $token = $this->iniciarSesion($this->crearUsuario());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/cerrar-sesion')->assertOk();

        $this->getConToken($token, '/api/v1/perfil')->assertUnauthorized();
    }

    public function test_usuario_desactivado_pierde_el_acceso_inmediatamente(): void
    {
        $administrador = $this->crearUsuario();
        $tecnico = $this->crearUsuario('tecnico');
        $token = $this->iniciarSesion($tecnico);

        $this->actingAs($administrador, 'sanctum')
            ->patchJson("/api/v1/usuarios/{$tecnico->id}/alternar-estado")
            ->assertOk();

        $this->getConToken($token, '/api/v1/perfil')->assertUnauthorized();
        $this->assertSame(0, $tecnico->tokens()->count());
    }

    public function test_administrador_no_puede_desactivarse_a_si_mismo(): void
    {
        $administrador = $this->crearUsuario();

        $this->actingAs($administrador, 'sanctum')
            ->patchJson("/api/v1/usuarios/{$administrador->id}", ['activo' => false])
            ->assertUnprocessable();

        $this->patchJson("/api/v1/usuarios/{$administrador->id}/alternar-estado")->assertForbidden();
    }

    public function test_recuperacion_de_contrasena_con_enlace_de_un_solo_uso(): void
    {
        $usuario = $this->crearUsuario();

        $this->postJson('/api/v1/recuperar-contrasena', ['correo' => $usuario->correo])->assertOk();
        // La respuesta no revela si el correo existe.
        $this->postJson('/api/v1/recuperar-contrasena', ['correo' => 'no-existe@sigma.test'])->assertOk();

        $token = null;
        Notification::assertSentTo($usuario, RestablecerContrasenaNotification::class, function ($notificacion) use (&$token) {
            $token = $notificacion->token;

            return true;
        });

        $datos = [
            'correo' => $usuario->correo,
            'token' => $token,
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ];

        $this->postJson('/api/v1/restablecer-contrasena', $datos)->assertOk();
        $this->postJson('/api/v1/restablecer-contrasena', $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token'], 'errores');

        $this->iniciarSesion($usuario, 'NuevaClave2026');
    }

    public function test_enlace_de_recuperacion_vence_a_los_60_minutos(): void
    {
        $usuario = $this->crearUsuario();
        $this->postJson('/api/v1/recuperar-contrasena', ['correo' => $usuario->correo])->assertOk();

        $token = null;
        Notification::assertSentTo($usuario, RestablecerContrasenaNotification::class, function ($notificacion) use (&$token) {
            $token = $notificacion->token;

            return true;
        });

        $this->travel(61)->minutes();

        $this->postJson('/api/v1/restablecer-contrasena', [
            'correo' => $usuario->correo,
            'token' => $token,
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ])->assertUnprocessable();
    }

    public function test_cambio_de_contrasena_exige_la_actual(): void
    {
        $usuario = $this->crearUsuario();
        $this->actingAs($usuario, 'sanctum');

        $this->putJson('/api/v1/perfil/contrasena', [
            'contrasena_actual' => 'equivocada1',
            'password' => 'OtraClave2026',
            'password_confirmation' => 'OtraClave2026',
        ])->assertUnprocessable()->assertJsonValidationErrors(['contrasena_actual'], 'errores');

        $this->putJson('/api/v1/perfil/contrasena', [
            'contrasena_actual' => 'clave12345',
            'password' => 'OtraClave2026',
            'password_confirmation' => 'OtraClave2026',
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->iniciarSesion($usuario, 'OtraClave2026');
    }

    public function test_verificacion_de_correo_con_enlace_firmado(): void
    {
        $administrador = $this->crearUsuario();
        $this->actingAs($administrador, 'sanctum')->postJson('/api/v1/usuarios', [
            'nombre' => 'Nuevo',
            'correo' => 'nuevo@sigma.test',
            'password' => 'clave12345',
            'rol_id' => Rol::where('nombre', 'tecnico')->value('id'),
        ])->assertCreated();

        $nuevo = Usuario::where('correo', 'nuevo@sigma.test')->firstOrFail();
        Notification::assertSentTo($nuevo, VerificarCorreoNotification::class);

        $this->get(VerificarCorreoNotification::enlace($nuevo))->assertRedirect();
        $this->assertNotNull($nuevo->fresh()->correo_verificado_en);

        $this->getJson("/api/v1/verificar-correo/{$nuevo->id}/".sha1($nuevo->correo))->assertForbidden();
    }

    public function test_cliente_solo_consulta_sus_propios_datos(): void
    {
        $propio = Cliente::create(['tipo' => 'juridica', 'nombre' => 'Propio', 'nit' => '900000001-1']);
        $ajeno = Cliente::create(['tipo' => 'juridica', 'nombre' => 'Ajeno', 'nit' => '900000002-2']);
        $datosEdificio = ['direccion' => 'Calle 1', 'ciudad' => 'Bogotá', 'nombre_contacto' => 'X', 'telefono_contacto' => '1'];
        $equipoPropio = $propio->edificios()->create(['nombre' => 'E1'] + $datosEdificio)
            ->equipos()->create(['codigo' => 'ASC-P', 'tipo' => 'ascensor']);
        $edificioAjeno = $ajeno->edificios()->create(['nombre' => 'E2'] + $datosEdificio);
        $equipoAjeno = $edificioAjeno->equipos()->create(['codigo' => 'ASC-A', 'tipo' => 'ascensor']);

        $usuarioCliente = $this->crearUsuario('cliente', ['cliente_id' => $propio->id]);
        $this->actingAs($usuarioCliente, 'sanctum');

        $this->getJson('/api/v1/clientes')->assertOk()->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.nombre', 'Propio');
        $this->getJson('/api/v1/equipos')->assertOk()->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.codigo', 'ASC-P');
        $this->getJson("/api/v1/equipos/{$equipoPropio->id}/historial")->assertOk();

        $this->getJson("/api/v1/clientes/{$ajeno->id}")->assertForbidden();
        $this->getJson("/api/v1/edificios/{$edificioAjeno->id}")->assertForbidden();
        $this->getJson("/api/v1/equipos/{$equipoAjeno->id}")->assertForbidden();
        $this->getJson("/api/v1/equipos/{$equipoAjeno->id}/historial")->assertForbidden();

        // El cliente no modifica datos.
        $this->patchJson("/api/v1/clientes/{$propio->id}", ['nombre' => 'Cambio'])->assertForbidden();
        $this->postJson("/api/v1/equipos/{$equipoPropio->id}/historial", ['evento' => 'x', 'descripcion' => 'y'])->assertForbidden();
    }

    public function test_supervisor_solo_consulta(): void
    {
        $supervisor = $this->crearUsuario('supervisor');
        $this->actingAs($supervisor, 'sanctum');

        $this->getJson('/api/v1/equipos')->assertOk();
        $this->postJson('/api/v1/clientes', ['tipo' => 'juridica', 'nombre' => 'X', 'nit' => '900000003'])->assertForbidden();
        $this->getJson('/api/v1/registros-auditoria')->assertForbidden();
    }

    public function test_registros_de_auditoria_e_historial_son_inmutables(): void
    {
        $usuario = $this->crearUsuario();
        $this->iniciarSesion($usuario);

        $registro = RegistroAuditoria::firstOrFail();
        $this->assertSame('127.0.0.1', $registro->ip);

        $this->expectException(LogicException::class);
        $registro->update(['accion' => 'alterado']);
    }

    public function test_respuestas_de_error_en_espanol(): void
    {
        $this->getJson('/api/v1/perfil')
            ->assertUnauthorized()
            ->assertJsonPath('mensaje', 'Sesión no válida o expirada. Inicie sesión nuevamente.');

        $this->actingAs($this->crearUsuario(), 'sanctum')
            ->getJson('/api/v1/clientes/999999')
            ->assertNotFound()
            ->assertJsonPath('mensaje', 'El recurso solicitado no existe.');

        $this->postJson('/api/v1/clientes', [])
            ->assertUnprocessable()
            ->assertJsonPath('errores.nombre.0', 'El campo nombre es obligatorio.');
    }
}
