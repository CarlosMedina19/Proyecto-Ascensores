<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Especialidad;
use App\Models\Rol;
use App\Models\TipoAscensor;
use App\Models\Usuario;
use App\Support\Excel\Plantilla;
use Carbon\CarbonImmutable;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\EspecialidadSeeder;
use Database\Seeders\PeriodicidadSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** Conjuntos, ubicación por departamento, catálogos de Configuración, horario de técnicos y plantillas por módulo. */
class PruebasConjuntosCatalogosTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $administrador;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00:00')); // lunes
        Notification::fake();
        Storage::fake('local');
        $this->seed([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class, EspecialidadSeeder::class]);
        $this->administrador = Usuario::factory()->create(['rol_id' => Rol::where('nombre', 'administrador')->value('id'), 'activo' => true]);
        $this->cliente = Cliente::factory()->create(['codigo' => 'CL-C01', 'nombre' => 'Administración Los Pinos']);
        $this->actingAs($this->administrador, 'sanctum');
    }

    private function conjunto(array $datos = []): array
    {
        return $this->postJson('/api/v1/conjuntos', $datos + [
            'cliente_id' => $this->cliente->id, 'tipo' => 'conjunto', 'nombre' => 'Conjunto Los Pinos',
            'direccion' => 'Calle 80 # 20-10', 'ciudad' => 'ibague', 'departamento' => 'tolima',
            'nombre_contacto' => 'Marta Ruiz', 'telefono_contacto' => '3105556677',
        ])->assertCreated()->json('datos');
    }

    public function test_la_ciudad_se_normaliza_y_debe_pertenecer_al_departamento(): void
    {
        $conjunto = $this->conjunto();
        $this->assertSame('Ibagué', $conjunto['ciudad']);
        $this->assertSame('Tolima', $conjunto['departamento']);

        $this->postJson('/api/v1/conjuntos', [
            'cliente_id' => $this->cliente->id, 'tipo' => 'conjunto', 'nombre' => 'Otro', 'direccion' => 'Calle 1', 'ciudad' => 'Medellín', 'departamento' => 'Tolima',
        ])->assertUnprocessable()->assertJsonValidationErrors(['ciudad'], 'errores');

        // Sin departamento, se deduce de la ciudad; Bogotá queda en Bogotá D.C.
        $this->postJson('/api/v1/conjuntos', [
            'cliente_id' => $this->cliente->id, 'tipo' => 'institucional', 'nombre' => 'Sede Norte', 'direccion' => 'Calle 100', 'ciudad' => 'bogota',
        ])->assertCreated()->assertJsonPath('datos.ciudad', 'Bogotá')->assertJsonPath('datos.departamento', 'Bogotá D.C.');

        $this->assertSame(['Cartagena de Indias', 'Bolívar'], \App\Support\Colombia::ubicacion('Cartagena', 'Bolivar'));

        $this->getJson('/api/v1/ubicaciones')->assertOk()->assertJsonPath('datos.Tolima.0', 'Alpujarra');
    }

    public function test_el_edificio_hereda_cliente_tipo_direccion_y_contacto_del_conjunto(): void
    {
        $conjunto = $this->conjunto();
        $otroCliente = Cliente::factory()->create();

        $edificio = $this->postJson('/api/v1/edificios', [
            'conjunto_id' => $conjunto['id'], 'cliente_id' => $otroCliente->id, 'tipo' => 'residencial', 'nombre' => 'Torre 2', 'pisos' => 12,
        ])->assertCreated()->json('datos');

        $this->assertSame($this->cliente->id, $edificio['cliente_id']);
        $this->assertSame('conjunto', $edificio['tipo']);
        $this->assertSame('Calle 80 # 20-10', $edificio['direccion']);
        $this->assertSame('Ibagué', $edificio['ciudad']);
        $this->assertSame('Marta Ruiz', $edificio['nombre_contacto']);
        $this->assertSame('Conjunto Los Pinos', $edificio['conjunto']['nombre']);

        // Si el conjunto cambia de tipo, sus edificios lo siguen.
        $this->patchJson("/api/v1/conjuntos/{$conjunto['id']}", ['tipo' => 'residencial'])->assertOk();
        $this->getJson("/api/v1/edificios/{$edificio['id']}")->assertJsonPath('datos.tipo', 'residencial');

        $this->getJson("/api/v1/conjuntos/{$conjunto['id']}")->assertOk()->assertJsonPath('datos.edificios_count', 1)->assertJsonPath('datos.edificios.0.nombre', 'Torre 2');
        $this->deleteJson("/api/v1/conjuntos/{$conjunto['id']}")->assertStatus(422);
    }

    public function test_el_administrador_mantiene_los_catalogos_y_lo_que_esta_en_uso_no_se_elimina(): void
    {
        $nuevo = $this->postJson('/api/v1/catalogos/tipos-ascensor', ['nombre' => 'Escalera eléctrica'])->assertCreated()->json('datos');
        $this->assertSame('escalera_electrica', $nuevo['clave']);
        $this->postJson('/api/v1/catalogos/tipos-ascensor', ['nombre' => 'Escalera eléctrica'])->assertUnprocessable();
        $this->getJson('/api/v1/tipos-ascensor')->assertOk()->assertJsonFragment(['clave' => 'escalera_electrica']);

        $edificio = $this->postJson('/api/v1/edificios', ['conjunto_id' => $this->conjunto()['id'], 'nombre' => 'Torre 1'])->assertCreated()->json('datos');
        $this->postJson('/api/v1/equipos', [
            'edificio_id' => $edificio['id'], 'codigo' => 'ESC-1',
            'ascensor' => ['tipo_ascensor' => 'escalera_electrica', 'capacidad_kg' => 500, 'velocidad_mpm' => 30, 'paradas' => 2],
        ])->assertCreated()->assertJsonPath('datos.ascensor.tipo_ascensor_texto', 'Escalera eléctrica');
        $this->postJson('/api/v1/equipos', [
            'edificio_id' => $edificio['id'], 'codigo' => 'ESC-2', 'ascensor' => ['tipo_ascensor' => 'no_existe', 'capacidad_kg' => 500, 'velocidad_mpm' => 30, 'paradas' => 2],
        ])->assertUnprocessable();

        $this->getJson('/api/v1/equipos')->assertOk()->assertJsonPath('por_tipo.escalera_electrica', 1);
        $this->getJson('/api/v1/equipos?tipo_ascensor=sin_tipo')->assertOk()->assertJsonCount(0, 'datos');

        $this->deleteJson("/api/v1/catalogos/tipos-ascensor/{$nuevo['id']}")->assertStatus(422);
        $this->patchJson("/api/v1/catalogos/tipos-ascensor/{$nuevo['id']}", ['activo' => false])->assertOk();
        $this->getJson('/api/v1/tipos-ascensor')->assertJsonMissing(['clave' => 'escalera_electrica']);

        $especialidad = $this->postJson('/api/v1/catalogos/especialidades', ['nombre' => 'Escaleras eléctricas'])->assertCreated()->json('datos');
        $this->deleteJson("/api/v1/catalogos/especialidades/{$especialidad['id']}")->assertOk();
        $this->assertNull(Especialidad::find($especialidad['id']));

        $coordinador = Usuario::factory()->create(['rol_id' => Rol::where('nombre', 'coordinador')->value('id'), 'activo' => true]);
        $this->actingAs($coordinador, 'sanctum')->postJson('/api/v1/catalogos/especialidades', ['nombre' => 'X'])->assertForbidden();
    }

    public function test_el_horario_del_tecnico_limita_su_disponibilidad(): void
    {
        $tecnico = $this->postJson('/api/v1/tecnicos', [
            'nombre' => 'Diana Mora', 'correo' => 'diana@sigma.test', 'password' => 'Clave1234', 'tipo_documento' => 'CC', 'numero_documento' => '10203040',
            'especialidades' => [Especialidad::value('id')],
            'horario' => ['1' => ['inicio' => '07:00', 'fin' => '17:00'], '6' => ['inicio' => '08:00', 'fin' => '12:00']],
        ])->assertCreated()->assertJsonPath('datos.horario_texto', 'Lun 07:00–17:00 · sáb 08:00–12:00')->json('datos');

        $this->postJson('/api/v1/tecnicos', [
            'nombre' => 'X', 'correo' => 'x@sigma.test', 'password' => 'Clave1234', 'tipo_documento' => 'CC', 'numero_documento' => '99887766',
            'especialidades' => [Especialidad::value('id')], 'horario' => ['2' => ['inicio' => '17:00', 'fin' => '08:00']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['horario.2.fin'], 'errores');

        $fila = fn (string $fecha, string $hora) => collect($this->getJson("/api/v1/tecnicos/disponibilidad?fecha={$fecha}&hora={$hora}&duracion=60")->json('datos'))
            ->firstWhere('usuario_id', $tecnico['usuario_id']);
        $this->assertTrue($fila('2026-11-02', '09:00')['asignable']); // lunes
        $this->assertSame('Fuera de su horario de trabajo', $fila('2026-11-03', '09:00')['motivo']); // martes
        $this->assertSame('Fuera de su horario de trabajo', $fila('2026-11-07', '11:30')['motivo']); // sábado, termina 12:30
    }

    public function test_la_plantilla_por_modulo_trae_solo_sus_hojas_y_las_listas_al_dia(): void
    {
        $this->postJson('/api/v1/catalogos/tipos-ascensor', ['nombre' => 'Escalera eléctrica'])->assertCreated();
        $respuesta = $this->get('/api/v1/excel/plantilla?modulo=tecnicos')->assertOk();
        $this->assertStringContainsString('SIGMA_Plantilla_Tecnicos.xlsx', $respuesta->headers->get('Content-Disposition'));
        $libro = IOFactory::load($respuesta->getFile()->getPathname());
        $this->assertSame(['Instrucciones', 'Diccionario', 'Técnicos', 'Listas'], $libro->getSheetNames());
        $diccionario = array_unique(array_column($libro->getSheetByName('Diccionario')->toArray(), 0));
        $this->assertSame(['Hoja', 'Técnicos'], array_values($diccionario));

        $completa = IOFactory::load($this->get('/api/v1/excel/plantilla')->assertOk()->getFile()->getPathname());
        $this->assertContains('Conjuntos', $completa->getSheetNames());
        $listas = $completa->getSheetByName('Listas');
        $tipos = array_filter(array_column($listas->rangeToArray(Plantilla::columnaLista('tiposAscensor').'2:'.Plantilla::columnaLista('tiposAscensor').'20'), 0));
        $this->assertContains('Escalera eléctrica', $tipos);
    }

    public function test_importa_conjuntos_edificios_con_conjunto_y_tecnicos_con_horario(): void
    {
        $libro = IOFactory::load(Plantilla::rutaArchivo());
        $escribir = function (string $clave, array $filas) use ($libro) {
            $hoja = $libro->getSheetByName(Plantilla::hoja($clave)['nombre']);
            foreach ($filas as $n => $fila) {
                foreach (Plantilla::hoja($clave)['columnas'] as $i => $columna) {
                    if (array_key_exists($columna['clave'], $fila)) {
                        $hoja->setCellValue(Plantilla::letra($i + 1).(3 + $n), $fila[$columna['clave']]);
                    }
                }
            }
        };
        $escribir('conjuntos', [['cliente' => 'CL-C01', 'nombre' => 'Conjunto Los Pinos', 'tipo' => 'Residencial', 'direccion' => 'Calle 80 # 20-10', 'departamento' => 'Tolima', 'ciudad' => 'Ibague', 'contacto' => 'Marta Ruiz', 'telefono' => '3105556677']]);
        $escribir('edificios', [
            ['cliente' => 'CL-C01', 'conjunto' => 'Conjunto Los Pinos', 'nombre' => 'Torre 1', 'pisos' => 10],
            ['cliente' => 'CL-C01', 'conjunto' => 'No existe', 'nombre' => 'Torre 9'],
        ]);
        $escribir('tecnicos', [
            ['nombre' => 'Felipe Gómez', 'correo' => 'felipe@sigma.test', 'tipo_documento' => 'CC', 'numero_documento' => '1020304050',
                'especialidades' => Especialidad::orderBy('id')->value('nombre'), 'dias' => 'Lunes a viernes, Sáb', 'entrada' => '07:00', 'salida' => '16:00'],
            ['nombre' => 'Sin especialidad', 'correo' => 'otro@sigma.test', 'tipo_documento' => 'CC', 'numero_documento' => '5566778899', 'especialidades' => 'Astronauta'],
        ]);
        $ruta = tempnam(sys_get_temp_dir(), 'prueba').'.xlsx';
        IOFactory::createWriter($libro, 'Xlsx')->save($ruta);

        $vista = $this->post('/api/v1/excel/importaciones', ['archivo' => new UploadedFile($ruta, 'carga.xlsx', null, null, true)])->assertOk();
        $this->postJson('/api/v1/excel/importaciones/'.$vista->json('datos.id').'/confirmar')->assertOk();

        $edificio = $this->cliente->edificios()->where('nombre', 'Torre 1')->first();
        $this->assertSame('residencial', $edificio->tipo);
        $this->assertSame('Ibagué', $edificio->ciudad);
        $this->assertSame('Marta Ruiz', $edificio->nombre_contacto);
        $this->assertSame('Conjunto Los Pinos', $edificio->conjunto->nombre);
        $this->assertNull($this->cliente->edificios()->where('nombre', 'Torre 9')->first());

        $tecnico = Usuario::where('correo', 'felipe@sigma.test')->first()->tecnico;
        $this->assertSame(['1', '2', '3', '4', '5', '6'], array_map('strval', array_keys($tecnico->horario)));
        $this->assertSame(['inicio' => '07:00', 'fin' => '16:00'], $tecnico->horario[6]);
        $this->assertNull(Usuario::where('correo', 'otro@sigma.test')->first());
        $this->assertSame(1, TipoAscensor::where('clave', 'pasajeros')->count());
    }
}
