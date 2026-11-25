<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\AlertaOperacionNotification;
use App\Support\Vigilancia;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Preparación para producción: respaldo fuera del servidor, vigilancia, salud y límite de la API. */
class PruebasOperacionTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('objetos');
        Storage::fake('local');
        $this->seed([RolSeeder::class, PermisoSeeder::class, ConfiguracionSeeder::class]);
        $this->carpeta = sys_get_temp_dir().'/sigma-respaldos-'.uniqid();
        mkdir($this->carpeta);
        config(['sigma.respaldos.carpeta' => $this->carpeta, 'sigma.respaldos.disco_remoto' => 'objetos', 'sigma.respaldos.vigilar' => true]);
        // El disco y la analítica reales de la máquina de pruebas no deben influir en el resultado.
        config(['sigma.vigilancia.disco_minimo' => 0, 'services.analitica.clave' => null]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->carpeta.'/*') ?: []);
        @rmdir($this->carpeta);
        parent::tearDown();
    }

    private function administrador(): Usuario
    {
        return Usuario::factory()->create(['rol_id' => Rol::where('nombre', 'administrador')->value('id'), 'activo' => true]);
    }

    public function test_el_respaldo_diario_se_sube_fuera_del_servidor_con_retencion(): void
    {
        file_put_contents("{$this->carpeta}/sigma_db_".now()->format('Ymd').'_0309.dump', 'volcado de hoy');
        file_put_contents("{$this->carpeta}/sigma_archivos_".now()->format('Ymd').'_0309.tar.gz', 'archivos de hoy');
        file_put_contents("{$this->carpeta}/otro-archivo.txt", 'no es un respaldo');
        $viejo = 'respaldos/sigma_db_'.now()->subDays(45)->format('Ymd').'_0309.dump';
        Storage::disk('objetos')->put($viejo, 'muy viejo');

        $this->artisan('sigma:respaldar-remoto')->assertSuccessful();

        $hoy = now()->format('Ymd');
        Storage::disk('objetos')->assertExists(["respaldos/sigma_db_{$hoy}_0309.dump", "respaldos/sigma_archivos_{$hoy}_0309.tar.gz"]);
        Storage::disk('objetos')->assertMissing(['respaldos/otro-archivo.txt', $viejo]);
        $this->assertNotNull(Vigilancia::ultimoExito('respaldo_remoto'));

        // Repetirlo no vuelve a subir lo que ya está.
        $this->artisan('sigma:respaldar-remoto')->expectsOutputToContain('0 subido(s)')->assertSuccessful();

        // Y se puede traer de vuelta para restaurar.
        $this->artisan('sigma:traer-respaldo')->assertSuccessful();
        Storage::disk('local')->assertExists("respaldos-descargados/sigma_db_{$hoy}_0309.dump");
    }

    public function test_sin_respaldos_locales_queda_registrado_el_fallo(): void
    {
        $this->artisan('sigma:respaldar-remoto')->assertFailed();
        $this->assertNotNull(Vigilancia::ultimoFallo('respaldo_remoto'));
    }

    public function test_la_vigilancia_avisa_a_los_administradores_una_vez_por_hora(): void
    {
        $administrador = $this->administrador();
        Vigilancia::registrarExito('respaldo_remoto');
        $this->artisan('sigma:vigilar')->expectsOutputToContain('Todo en orden')->assertSuccessful();

        // Un trabajo fallido y una cola detenida hace 15 minutos.
        DB::table('failed_jobs')->insert(['uuid' => 'u-1', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'Error de prueba', 'failed_at' => now()]);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(15)->timestamp, 'created_at' => now()->subMinutes(15)->timestamp]);
        config(['sigma.vigilancia.revisar_cola' => true]);

        $this->artisan('sigma:vigilar')->assertFailed();
        Notification::assertSentTo($administrador, AlertaOperacionNotification::class, fn ($n) => $n->problema === 'Trabajos fallidos');
        Notification::assertSentTo($administrador, AlertaOperacionNotification::class, fn ($n) => $n->problema === 'Cola detenida');
        $avisos = Notification::sent($administrador, AlertaOperacionNotification::class)->count();

        // La siguiente revisión (5 minutos después) no repite los avisos.
        $this->artisan('sigma:vigilar')->assertFailed();
        $this->assertSame($avisos, Notification::sent($administrador, AlertaOperacionNotification::class)->count());
    }

    public function test_las_alertas_tambien_llegan_por_correo_una_vez_por_hora(): void
    {
        config(['sigma.vigilancia.correo_alertas' => 'alertas@sigma.test']);
        Vigilancia::registrarExito('respaldo_remoto');
        $this->travel(31)->hours();

        $this->artisan('sigma:vigilar')->assertFailed();
        Notification::assertSentOnDemand(AlertaOperacionNotification::class, function ($n, array $canales, $destino) {
            return $n->problema === 'Respaldo remoto atrasado' && $canales === ['mail'] && $destino->routes['mail'] === 'alertas@sigma.test';
        });
        $this->artisan('sigma:vigilar')->assertFailed();
        Notification::assertSentOnDemandTimes(AlertaOperacionNotification::class, 1);

        // El correo se arma aunque el destino no sea un usuario de SIGMA.
        $correo = (new AlertaOperacionNotification('Disco casi lleno', 'Queda 5 % libre.'))->toMail(new AnonymousNotifiable);
        $this->assertSame('Hola, equipo de SIGMA', $correo->greeting);
        $this->assertStringContainsString('Disco casi lleno', $correo->subject);
    }

    public function test_la_vigilancia_avisa_si_el_respaldo_remoto_esta_atrasado(): void
    {
        $administrador = $this->administrador();
        Vigilancia::registrarExito('respaldo_remoto');
        $this->travel(31)->hours();

        $this->artisan('sigma:vigilar')->assertFailed();
        Notification::assertSentTo($administrador, AlertaOperacionNotification::class, fn ($n) => $n->problema === 'Respaldo remoto atrasado');
    }

    public function test_el_chequeo_de_salud_y_el_limite_general_de_la_api(): void
    {
        $this->get('/up')->assertOk();

        config(['sigma.limite_api_por_minuto' => 3]);
        $this->actingAs($this->administrador(), 'sanctum');
        foreach (range(1, 3) as $_) {
            $this->getJson('/api/v1/perfil')->assertOk();
        }
        $this->getJson('/api/v1/perfil')->assertStatus(429)->assertJsonPath('mensaje', 'Demasiadas solicitudes seguidas. Espere un momento.');
    }
}
