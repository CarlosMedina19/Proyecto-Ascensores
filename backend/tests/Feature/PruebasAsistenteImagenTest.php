<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as SolicitudHttp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Chat del panel con fotos del ascensor tomadas con la cámara o elegidas de la galería. */
class PruebasAsistenteImagenTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $coordinador;

    private Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolSeeder::class, PermisoSeeder::class, ConfiguracionSeeder::class, ChecklistSeeder::class]);
        $this->coordinador = Usuario::factory()->create(['rol_id' => Rol::where('nombre', 'coordinador')->value('id'), 'activo' => true]);
        $cliente = Cliente::factory()->create();
        $this->equipo = Equipo::factory()->create(['edificio_id' => Edificio::factory()->create(['cliente_id' => $cliente->id])->id, 'codigo' => 'ASC-901']);
        $this->actingAs($this->coordinador, 'sanctum');
    }

    /** JPG de 240×180 generado con GD: «oxido» (naranja-marrón con textura), «oscura» o «nitida» (cuadrícula gris). */
    private function foto(string $tipo): UploadedFile
    {
        $imagen = imagecreatetruecolor(240, 180);
        for ($y = 0; $y < 180; $y++) {
            for ($x = 0; $x < 240; $x++) {
                [$r, $g, $b] = match ($tipo) {
                    'oxido' => [150 + ($x * 7 + $y * 3) % 40, 70 + ($x * 5) % 25, 25 + ($y * 3) % 15],
                    'oscura' => [12 + ($x % 6), 12 + ($y % 6), 14],
                    default => (intdiv($x, 6) + intdiv($y, 6)) % 2 ? [95, 100, 105] : [170, 172, 175],
                };
                imagesetpixel($imagen, $x, $y, imagecolorallocate($imagen, $r, $g, $b));
            }
        }
        ob_start();
        imagejpeg($imagen, null, 92);
        $contenido = ob_get_clean();
        imagedestroy($imagen);

        return UploadedFile::fake()->createWithContent("{$tipo}.jpg", $contenido);
    }

    private function enviar(array $datos)
    {
        return $this->post('/api/v1/asistente', $datos + ['sesion' => 'foto1'], ['Accept' => 'application/json']);
    }

    public function test_analiza_la_foto_sin_ia_y_sugiere_que_revisar_segun_la_planilla(): void
    {
        $datos = $this->enviar(['mensaje' => 'Así está la puerta de piso del ASC-901', 'imagen' => $this->foto('oxido')])->assertOk()->json('datos');

        $this->assertSame('local', $datos['origen']);
        $this->assertSame(['estado' => 'revisar', 'zona' => 'puertas'], $datos['analisis']);
        $this->assertStringContainsString('óxido', $datos['texto']);
        $this->assertStringContainsString('Mecanismos de puertas', $datos['texto']);
        $this->assertStringContainsString('PT-04', $datos['texto'], 'primero las actividades críticas de la sección');
        $this->assertStringContainsString('Ascensor ASC-901', $datos['texto']);
        $rutas = array_column($datos['acciones'], 'ruta');
        $this->assertContains("/equipos/{$this->equipo->id}", $rutas);
        $this->assertContains('/ordenes/nueva', $rutas);
    }

    public function test_pide_repetir_una_foto_oscura_y_acepta_una_foto_clara(): void
    {
        $oscura = $this->enviar(['imagen' => $this->foto('oscura')])->assertOk()->json('datos');
        $this->assertSame('repetir', $oscura['analisis']['estado']);
        $this->assertStringContainsString('muy oscura', $oscura['texto']);
        $this->assertNotEmpty($oscura['sugerencias'], 'sin zona, ofrece elegirla');

        $clara = $this->enviar(['mensaje' => 'cuarto de máquinas', 'imagen' => $this->foto('nitida')])->assertOk()->json('datos');
        $this->assertSame(['estado' => 'bien', 'zona' => 'cuarto_maquinas'], $clara['analisis']);
    }

    public function test_solo_acepta_imagenes_y_un_mensaje_o_una_foto(): void
    {
        $this->enviar(['imagen' => UploadedFile::fake()->create('informe.pdf', 20, 'application/pdf')])->assertStatus(422);
        $this->enviar([])->assertStatus(422);
    }

    public function test_con_n8n_activo_la_foto_va_al_modelo_con_vision(): void
    {
        config(['services.asistente' => array_merge(config('services.asistente'), ['n8n_activo' => true, 'url_panel' => 'https://n8n.test/webhook/sigma-panel', 'clave' => 'clave-n8n'])]);
        Http::fake(['n8n.test/*' => Http::response(['respuesta' => 'Se observa corrosión en la cerradura.'])]);

        $datos = $this->enviar(['mensaje' => 'revisa esta puerta', 'imagen' => $this->foto('oxido')])->assertOk()->json('datos');

        $this->assertSame(['texto' => 'Se observa corrosión en la cerradura.', 'origen' => 'n8n'], ['texto' => $datos['texto'], 'origen' => $datos['origen']]);
        Http::assertSent(fn (SolicitudHttp $s) => str_starts_with((string) ($s['imagen']['url'] ?? ''), 'data:image/jpeg;base64,') && $s['mensaje'] === 'revisa esta puerta');
    }
}
