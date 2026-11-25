<?php

namespace Tests\Feature;

use App\Models\CodigoVerificacion;
use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\CodigoVerificacionNotification;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as SolicitudHttp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Verificación en dos pasos por correo y recuperación de la contraseña con el número de teléfono. */
class PruebasDobleFactorTelefonoTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Clave-segura-2026';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed([RolSeeder::class, PermisoSeeder::class, ConfiguracionSeeder::class]);
    }

    private function usuario(string $rol = 'administrador', array $datos = []): Usuario
    {
        return Usuario::factory()->create($datos + [
            'rol_id' => Rol::where('nombre', $rol)->value('id'),
            'password' => self::CLAVE,
            'activo' => true,
        ]);
    }

    private function iniciar(Usuario $usuario, string $clave = self::CLAVE)
    {
        return $this->postJson('/api/v1/iniciar-sesion', ['correo' => $usuario->correo, 'password' => $clave]);
    }

    private function codigoDelCorreo(Usuario $usuario): string
    {
        $codigo = null;
        Notification::assertSentTo($usuario, CodigoVerificacionNotification::class, function ($notificacion) use (&$codigo) {
            $codigo = $notificacion->codigo;

            return true;
        });

        return $codigo;
    }

    public function test_con_doble_factor_el_ingreso_pide_el_codigo_enviado_al_correo(): void
    {
        $usuario = $this->usuario(datos: ['correo' => 'sigma3172409@gmail.com', 'doble_factor' => true]);

        $paso1 = $this->iniciar($usuario)->assertOk()
            ->assertJsonPath('requiere_verificacion', true)
            ->assertJsonPath('canal', 'correo')
            ->assertJsonPath('destino', 'si***09@gmail.com')
            ->assertJsonMissingPath('token_acceso');
        $codigo = $this->codigoDelCorreo($usuario);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $codigo);
        $this->assertNotSame($codigo, CodigoVerificacion::first()->codigo, 'en la base solo queda la huella del código');

        $desafio = $paso1->json('desafio');
        $errado = $codigo === '000000' ? '111111' : '000000';
        $this->postJson('/api/v1/iniciar-sesion/verificar', ['desafio' => $desafio, 'codigo' => $errado])
            ->assertStatus(422)->assertJsonValidationErrors('codigo', 'errores');

        $this->postJson('/api/v1/iniciar-sesion/verificar', ['desafio' => $desafio, 'codigo' => $codigo, 'dispositivo' => 'panel'])
            ->assertOk()->assertJsonStructure(['token_acceso', 'usuario' => ['id', 'doble_factor']]);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'inicio_sesion', 'usuario_id' => $usuario->id]);

        // El código es de un solo uso.
        $this->postJson('/api/v1/iniciar-sesion/verificar', ['desafio' => $desafio, 'codigo' => $codigo])->assertStatus(422);
    }

    public function test_cinco_codigos_errados_anulan_la_solicitud_y_reenviar_espera_un_minuto(): void
    {
        $usuario = $this->usuario(datos: ['doble_factor' => true]);
        $desafio = $this->iniciar($usuario)->json('desafio');
        $codigo = $this->codigoDelCorreo($usuario);
        $errado = $codigo === '000000' ? '111111' : '000000';

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/iniciar-sesion/verificar', ['desafio' => $desafio, 'codigo' => $errado])->assertStatus(422);
        }
        $this->postJson('/api/v1/iniciar-sesion/verificar', ['desafio' => $desafio, 'codigo' => $codigo])
            ->assertStatus(422)->assertJsonPath('errores.codigo.0', 'Superó los intentos permitidos. Solicite un código nuevo.');

        $this->postJson('/api/v1/codigo-verificacion/reenviar', ['desafio' => $desafio])->assertStatus(422);
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/codigo-verificacion/reenviar', ['desafio' => $desafio])->assertOk()->assertJsonPath('canal', 'correo');
        Notification::assertSentToTimes($usuario, CodigoVerificacionNotification::class, 2);
    }

    public function test_sin_doble_factor_el_ingreso_sigue_igual_y_el_usuario_lo_activa_con_su_contrasena(): void
    {
        $usuario = $this->usuario();
        $token = $this->iniciar($usuario)->assertOk()->assertJsonMissingPath('requiere_verificacion')->json('token_acceso');
        $this->assertNotEmpty($token);
        Notification::assertNotSentTo($usuario, CodigoVerificacionNotification::class);

        $this->withToken($token)->putJson('/api/v1/perfil/doble-factor', ['activo' => true, 'password' => 'otra-clave-1'])->assertStatus(422);
        $this->withToken($token)->putJson('/api/v1/perfil/doble-factor', ['activo' => true, 'password' => self::CLAVE])->assertOk()->assertJsonPath('doble_factor', true);
        $this->assertTrue($usuario->fresh()->doble_factor);
        $this->iniciar($usuario)->assertJsonPath('requiere_verificacion', true);
    }

    public function test_recupera_la_contrasena_con_el_codigo_que_llega_por_sms(): void
    {
        config(['services.sms' => ['driver' => 'twilio', 'twilio' => ['sid' => 'AC123', 'token' => 'tk', 'numero' => '+15005550006'], 'n8n' => []]]);
        $mensaje = null;
        Http::fake(['api.twilio.com/*' => function (SolicitudHttp $s) use (&$mensaje) {
            $mensaje = $s['Body'];

            return Http::response(['sid' => 'SM1'], 201);
        }]);
        $tecnico = $this->usuario('tecnico', ['telefono' => '3001234567']);
        $tecnico->createToken('app');

        // Un número sin cuenta recibe la misma respuesta y no se envía nada.
        $this->postJson('/api/v1/recuperar-contrasena/telefono', ['telefono' => '311 000 0000'])->assertOk()->assertJsonStructure(['desafio', 'destino', 'mensaje']);
        Http::assertNothingSent();
        $this->postJson('/api/v1/recuperar-contrasena/telefono', ['telefono' => '12345'])->assertStatus(422);

        $desafio = $this->postJson('/api/v1/recuperar-contrasena/telefono', ['telefono' => '+57 300 123 4567'])
            ->assertOk()->assertJsonPath('destino', '300 *** **67')->assertJsonMissingPath('codigo_demo')->json('desafio');
        Http::assertSent(fn (SolicitudHttp $s) => $s['To'] === '+573001234567' && $s['From'] === '+15005550006');
        preg_match('/\b(\d{6})\b/', (string) $mensaje, $m);
        $this->assertNotEmpty($m[1] ?? null);

        $this->postJson('/api/v1/restablecer-contrasena/telefono', ['desafio' => $desafio, 'codigo' => $m[1], 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026'])->assertOk();
        $this->assertTrue(Hash::check('NuevaClave2026', $tecnico->fresh()->password));
        $this->assertSame(0, $tecnico->tokens()->count(), 'se cierran las sesiones abiertas');
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'contrasena_restablecida', 'usuario_id' => $tecnico->id]);
    }

    public function test_el_modo_simulado_muestra_el_codigo_salvo_en_cuentas_protegidas(): void
    {
        config(['services.sms.driver' => 'simulado']);
        $tecnico = $this->usuario('tecnico', ['telefono' => '3001112233']);
        $this->usuario('administrador', ['telefono' => '3009998877']);

        $respuesta = $this->postJson('/api/v1/recuperar-contrasena/telefono', ['telefono' => '3001112233'])->assertOk();
        $this->postJson('/api/v1/restablecer-contrasena/telefono', [
            'desafio' => $respuesta->json('desafio'), 'codigo' => $respuesta->json('codigo_demo'),
            'password' => 'OtraClave2026', 'password_confirmation' => 'OtraClave2026',
        ])->assertOk();
        $this->assertTrue(Hash::check('OtraClave2026', $tecnico->fresh()->password));

        $this->postJson('/api/v1/recuperar-contrasena/telefono', ['telefono' => '3009998877'])->assertOk()
            ->assertJsonMissingPath('codigo_demo')->assertJsonStructure(['nota']);
    }

    public function test_en_produccion_el_modo_simulado_no_muestra_el_codigo_y_desactiva_el_celular(): void
    {
        $this->app['env'] = 'production';
        config(['services.sms.driver' => 'simulado']);
        $this->usuario('coordinador', ['telefono' => '3001112233']);

        // Mostrar el código permitiría tomar la cuenta de quien tenga ese celular.
        $this->getJson('/api/v1/publico/acceso')->assertOk()->assertJsonPath('datos.sms_disponible', false);
        $this->postJson('/api/v1/recuperar-contrasena/telefono', ['telefono' => '3001112233'])->assertStatus(503)
            ->assertJsonMissingPath('codigo_demo')->assertJsonMissingPath('desafio');
        // La misma respuesta para un número que no existe: no revela qué celulares están registrados.
        $this->postJson('/api/v1/recuperar-contrasena/telefono', ['telefono' => '3005550000'])->assertStatus(503);
        $this->assertSame(0, CodigoVerificacion::count());

        // Solo con el permiso explícito para una presentación vuelve a mostrarse.
        config(['services.sms.demo_en_produccion' => true]);
        $this->postJson('/api/v1/recuperar-contrasena/telefono', ['telefono' => '3001112233'])->assertOk()->assertJsonStructure(['codigo_demo']);
    }

    public function test_en_produccion_con_operador_real_la_recuperacion_por_celular_sigue_disponible(): void
    {
        $this->app['env'] = 'production';
        config(['services.sms' => ['driver' => 'twilio', 'demo_en_produccion' => false, 'twilio' => ['sid' => 'AC123', 'token' => 'tk', 'numero' => '+15005550006'], 'n8n' => []]]);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
        $this->usuario('coordinador', ['telefono' => '3001112233']);

        $this->getJson('/api/v1/publico/acceso')->assertOk()->assertJsonPath('datos.sms_disponible', true);
        $this->postJson('/api/v1/recuperar-contrasena/telefono', ['telefono' => '3001112233'])->assertOk()->assertJsonMissingPath('codigo_demo');
        Http::assertSentCount(1);
    }

    /** Verifica el código en un dispositivo y devuelve su llave de confianza. */
    private function verificarEnDispositivoNuevo(Usuario $usuario): string
    {
        $desafio = $this->iniciar($usuario)->assertJsonPath('requiere_verificacion', true)->json('desafio');
        $codigo = null;
        Notification::assertSentTo($usuario, CodigoVerificacionNotification::class, function ($n) use (&$codigo) {
            $codigo = $n->codigo;

            return true;
        });

        return $this->postJson('/api/v1/iniciar-sesion/verificar', ['desafio' => $desafio, 'codigo' => $codigo, 'dispositivo' => 'web'])
            ->assertOk()->assertJsonStructure(['token_acceso', 'dispositivo_confianza'])->json('dispositivo_confianza');
    }

    public function test_en_un_dispositivo_ya_verificado_no_vuelve_a_pedir_el_codigo(): void
    {
        $usuario = $this->usuario(datos: ['doble_factor' => true]);
        $llave = $this->verificarEnDispositivoNuevo($usuario);

        // Mismo dispositivo: entra directo y no se envía otro código.
        $this->postJson('/api/v1/iniciar-sesion', ['correo' => $usuario->correo, 'password' => self::CLAVE, 'dispositivo_confianza' => $llave])
            ->assertOk()->assertJsonStructure(['token_acceso'])->assertJsonMissingPath('requiere_verificacion');
        Notification::assertSentToTimes($usuario, CodigoVerificacionNotification::class, 1);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'inicio_sesion', 'usuario_id' => $usuario->id]);

        // La contraseña sigue siendo obligatoria.
        $this->postJson('/api/v1/iniciar-sesion', ['correo' => $usuario->correo, 'password' => 'otra-clave-1', 'dispositivo_confianza' => $llave])->assertStatus(401);

        // Otro dispositivo, o la llave de otra cuenta, vuelve a pedir el código.
        $this->postJson('/api/v1/iniciar-sesion', ['correo' => $usuario->correo, 'password' => self::CLAVE, 'dispositivo_confianza' => str_repeat('x', 64)])
            ->assertJsonPath('requiere_verificacion', true);
        $otro = $this->usuario(datos: ['doble_factor' => true]);
        $this->postJson('/api/v1/iniciar-sesion', ['correo' => $otro->correo, 'password' => self::CLAVE, 'dispositivo_confianza' => $llave])
            ->assertJsonPath('requiere_verificacion', true);

        // La confianza vence a los 180 días.
        $this->travel(181)->days();
        $this->postJson('/api/v1/iniciar-sesion', ['correo' => $usuario->correo, 'password' => self::CLAVE, 'dispositivo_confianza' => $llave])
            ->assertJsonPath('requiere_verificacion', true);
    }

    public function test_olvidar_los_dispositivos_o_restablecer_la_contrasena_vuelve_a_pedir_el_codigo(): void
    {
        $usuario = $this->usuario('coordinador', ['doble_factor' => true, 'telefono' => '3001234567']);
        $llave = $this->verificarEnDispositivoNuevo($usuario);
        $token = $this->postJson('/api/v1/iniciar-sesion', ['correo' => $usuario->correo, 'password' => self::CLAVE, 'dispositivo_confianza' => $llave])->json('token_acceso');

        $this->withToken($token)->getJson('/api/v1/perfil')->assertJsonPath('datos.dispositivos_confianza', 1);
        $this->withToken($token)->deleteJson('/api/v1/perfil/dispositivos-confianza')->assertOk();
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'dispositivos_olvidados', 'usuario_id' => $usuario->id]);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/iniciar-sesion', ['correo' => $usuario->correo, 'password' => self::CLAVE, 'dispositivo_confianza' => $llave])
            ->assertJsonPath('requiere_verificacion', true);

        // Una contraseña restablecida también olvida los dispositivos.
        $usuario->dispositivosConfianza()->create(['huella' => hash_hmac('sha256', 'dispositivo|otra-llave', (string) config('app.key')), 'expira_en' => now()->addDays(30)]);
        config(['services.sms.driver' => 'simulado']);
        $solicitud = $this->postJson('/api/v1/recuperar-contrasena/telefono', ['telefono' => '3001234567'])->json();
        $this->assertArrayNotHasKey('codigo_demo', $solicitud, 'cuenta con dos pasos: el código no se muestra');
        $usuario->forceFill(['doble_factor' => false])->save();
        $solicitud = $this->postJson('/api/v1/recuperar-contrasena/telefono', ['telefono' => '3001234567'])->json();
        $this->postJson('/api/v1/restablecer-contrasena/telefono', ['desafio' => $solicitud['desafio'], 'codigo' => $solicitud['codigo_demo'], 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026'])->assertOk();
        $this->assertSame(0, $usuario->dispositivosConfianza()->count());
    }

    public function test_el_celular_del_perfil_se_valida_y_no_se_repite(): void
    {
        $this->usuario('coordinador', ['telefono' => '3015550000']);
        $usuario = $this->usuario('tecnico');
        $this->actingAs($usuario, 'sanctum');

        $this->patchJson('/api/v1/perfil', ['telefono' => 'abc'])->assertStatus(422);
        $this->patchJson('/api/v1/perfil', ['telefono' => '301 555 0000'])->assertStatus(422);
        $this->patchJson('/api/v1/perfil', ['telefono' => '+57 300 765 4321'])->assertOk()->assertJsonPath('datos.telefono', '3007654321');

        $this->actingAs($this->usuario(), 'sanctum');
        $this->postJson('/api/v1/usuarios', [
            'nombre' => 'Nuevo', 'correo' => 'nuevo@sigma.test', 'password' => 'Clave12345', 'rol_id' => Rol::where('nombre', 'coordinador')->value('id'),
            'telefono' => '300 765 4321',
        ])->assertStatus(422)->assertJsonValidationErrors('telefono', 'errores');
    }
}
