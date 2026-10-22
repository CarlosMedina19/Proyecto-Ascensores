<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Equipo;
use App\Models\Factura;
use App\Models\Periodicidad;
use App\Models\PuertaPiso;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\PlanService;
use App\Support\Excel\Plantilla;
use Carbon\CarbonImmutable;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PeriodicidadSeeder;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** Plantilla de Excel, exportación e importación en dos pasos (vista previa y confirmación). */
class PruebasExcelTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-01-05 10:00:00'));
        Notification::fake();
        Storage::fake('local');
        $this->seed([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class]);
        $this->administrador = $this->usuario('administrador');
        app(PlanService::class)->crear([
            'nombre' => 'Plan Integral',
            'activo' => true,
            'precios' => [['periodicidad_id' => Periodicidad::where('meses', 1)->value('id'), 'precio' => 380000]],
        ]);
    }

    private function usuario(string $rol): Usuario
    {
        return Usuario::factory()->create(['rol_id' => Rol::where('nombre', $rol)->value('id'), 'activo' => true]);
    }

    /**
     * Arma un .xlsx a partir de la plantilla con las filas dadas por hoja (claves de columna → valor).
     *
     * @param  array<string, array<int, array<string, mixed>>>  $hojas
     */
    private function archivo(array $hojas, bool $conEjemplos = true): UploadedFile
    {
        $libro = IOFactory::load(Plantilla::rutaArchivo());
        foreach ($hojas as $clave => $filas) {
            $hoja = $libro->getSheetByName(Plantilla::hoja($clave)['nombre']);
            $columnas = Plantilla::hoja($clave)['columnas'];
            $inicio = $conEjemplos ? 3 : 2; // la fila 2 es el ejemplo
            foreach ($filas as $n => $fila) {
                foreach ($columnas as $i => $columna) {
                    if (array_key_exists($columna['clave'], $fila)) {
                        $hoja->setCellValue(Plantilla::letra($i + 1).($inicio + $n), $fila[$columna['clave']]);
                    }
                }
            }
        }
        $ruta = tempnam(sys_get_temp_dir(), 'prueba').'.xlsx';
        IOFactory::createWriter($libro, 'Xlsx')->save($ruta);

        return new UploadedFile($ruta, 'carga.xlsx', null, null, true);
    }

    private function datosBasicos(): array
    {
        return [
            'clientes' => [[
                'codigo' => 'CL-T01', 'nombre' => 'Conjunto Prueba', 'tipo' => 'Persona jurídica', 'documento' => '900555111-2',
                'contacto' => 'Laura Gómez', 'telefono' => '3101112233', 'correo' => 'admin@prueba.co',
                'vigencia_desde' => '05/01/2026', 'vigencia_hasta' => '31/12/2026', 'valor_contrato' => 12000000,
            ]],
            'edificios' => [[
                'cliente' => 'CL-T01', 'nombre' => 'Torre 1', 'tipo' => 'Conjunto', 'direccion' => 'Calle 1 # 2-3',
                'ciudad' => 'Ibagué', 'contacto' => 'Laura Gómez', 'telefono' => '3101112233',
            ]],
            'ascensores' => [
                [
                    'codigo' => 'ASC-T1', 'cliente' => 'CL-T01', 'edificio' => 'Torre 1', 'nombre' => 'Ascensor 1', 'marca' => 'Schindler',
                    'anio_instalacion' => 2018, 'capacidad_kg' => 630, 'personas' => 8, 'velocidad_ms' => '1,0', 'paradas' => 7,
                    'periodicidad' => 1, 'fecha_primer_mantenimiento' => '10/01/2026', 'valor_visita' => '$ 380.000',
                ],
                // Inválido: capacidad fuera de rango y paradas faltantes.
                ['codigo' => 'ASC-T2', 'cliente' => 'CL-T01', 'edificio' => 'Torre 1', 'capacidad_kg' => 9000, 'velocidad_ms' => 1],
            ],
            'puertas' => [['ascensor' => 'ASC-T1', 'piso' => 6, 'tipo' => 'Automática', 'estado' => 'Regular', 'observacion' => 'Cierre lento.']],
            'facturas' => [['cliente' => 'CL-T01', 'numero' => 'FV-1', 'fecha_emision' => '01/01/2026', 'fecha_vencimiento' => '15/01/2026', 'valor' => 850000]],
            'pagos' => [['cliente' => 'CL-T01', 'factura' => 'FV-1', 'valor' => 500000, 'fecha' => '04/01/2026', 'medio' => 'Transferencia', 'referencia' => 'TRF-1']],
        ];
    }

    public function test_descarga_la_plantilla_vacia(): void
    {
        $this->actingAs($this->administrador, 'sanctum')
            ->get('/api/v1/excel/plantilla')
            ->assertOk()
            ->assertDownload('SIGMA_Plantilla_Datos.xlsx');
    }

    public function test_la_vista_previa_valida_sin_guardar_y_la_confirmacion_guarda_las_filas_validas(): void
    {
        $this->actingAs($this->administrador, 'sanctum');

        $vista = $this->post('/api/v1/excel/importaciones', ['archivo' => $this->archivo($this->datosBasicos())], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('datos.totales.errores', 1)
            ->assertJsonPath('datos.derivados.puertas_creadas', 7)
            ->assertJsonPath('datos.derivados.contratos_creados', 1);

        // El error trae hoja, fila y columna; la fila de ejemplo (fila 2) se ignoró.
        $errores = collect($vista->json('datos.errores'));
        $this->assertTrue($errores->contains(fn ($e) => $e['hoja'] === 'Ascensores' && $e['fila'] === 4 && $e['columna'] === 'Paradas'));
        $this->assertTrue($errores->contains(fn ($e) => $e['fila'] === 4 && $e['columna'] === 'Capacidad (kg)'));

        // Nada se guardó todavía.
        $this->assertDatabaseMissing('clientes', ['codigo' => 'CL-T01']);
        $this->assertSame(0, Equipo::count());

        $id = $vista->json('datos.id');
        $this->postJson("/api/v1/excel/importaciones/{$id}/confirmar")
            ->assertOk()
            ->assertJsonPath('datos.mensaje', 'Se importaron 1 cliente, 1 edificio, 1 ascensor, 7 puertas de piso, 1 factura, 1 pago y 1 contrato. Se actualizó 1 registro existente. 1 fila no se cargó.');

        $equipo = Equipo::where('codigo', 'ASC-T1')->firstOrFail();
        $this->assertSame(7, $equipo->puertasPiso()->count());
        $this->assertSame('R', PuertaPiso::where('equipo_id', $equipo->id)->where('piso', 6)->value('estado'));
        $this->assertEquals(60.0, (float) $equipo->ascensor->velocidad_mpm); // 1,0 m/s
        $this->assertTrue($equipo->asignacionActiva()->exists());
        $this->assertEquals(350000, (float) Factura::where('numero', 'FV-1')->value('saldo'));
        $this->assertSame(1, Cliente::where('codigo', 'CL-T01')->firstOrFail()->contratos()->count());
        $this->assertDatabaseMissing('equipos', ['codigo' => 'ASC-T2']);
    }

    public function test_detecta_duplicados_en_el_archivo_y_rechaza_pagos_mayores_al_saldo(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $datos = $this->datosBasicos();
        $datos['clientes'][] = $datos['clientes'][0];
        $datos['pagos'][0]['valor'] = 999999999;

        $vista = $this->post('/api/v1/excel/importaciones', ['archivo' => $this->archivo($datos)], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(1, $vista->json('datos.totales.duplicados'));
        $this->assertStringContainsString('ya aparece en la fila 3', $vista->json('datos.duplicados.0.motivo'));
        $this->assertTrue(collect($vista->json('datos.errores'))->contains(fn ($e) => $e['hoja'] === 'Pagos' && str_contains($e['motivo'], 'supera el saldo')));
    }

    public function test_importa_certificaciones_emergencias_y_cotizaciones_y_las_exporta(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $datos = $this->datosBasicos() + [
            'certificaciones' => [
                ['ascensor' => 'ASC-T1', 'organismo' => 'Inspecciones Andinas', 'fecha_inspeccion' => '10/03/2025', 'fecha_vencimiento' => '10/03/2026', 'resultado' => 'Conforme'],
                // Inválido: el vencimiento es anterior a la inspección.
                ['ascensor' => 'ASC-T1', 'organismo' => 'Otro', 'fecha_inspeccion' => '01/12/2025', 'fecha_vencimiento' => '01/11/2025', 'resultado' => 'Conforme'],
            ],
            'emergencias' => [
                [
                    'ascensor' => 'ASC-T1', 'tipo' => 'Personas atrapadas', 'fecha_reporte' => '02/01/2026', 'hora_reporte' => '19:40',
                    'reportante' => 'Portería', 'hora_llegada' => '20:05', 'fecha_solucion' => '02/01/2026', 'hora_solucion' => '20:30', 'solucion' => 'Rescate.',
                ],
                // Inválido: tiene fecha de solución pero no dice qué se hizo.
                ['ascensor' => 'ASC-T1', 'tipo' => 'Ruido o movimiento anormal', 'fecha_reporte' => '03/01/2026', 'hora_reporte' => '08:00', 'fecha_solucion' => '03/01/2026'],
            ],
            'cotizaciones' => [['ascensor' => 'ASC-T1', 'titulo' => 'Cambio de amortiguadores', 'valor' => 2000000, 'iva' => 19, 'vigente_hasta' => '31/01/2026']],
        ];

        $id = $this->post('/api/v1/excel/importaciones', ['archivo' => $this->archivo($datos)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('datos.totales.errores', 3)
            ->json('datos.id');
        $this->postJson("/api/v1/excel/importaciones/{$id}/confirmar")->assertOk();

        $equipo = Equipo::where('codigo', 'ASC-T1')->firstOrFail();
        $this->assertSame(1, $equipo->certificaciones()->count());
        $emergencia = $equipo->emergencias()->firstOrFail();
        $this->assertSame('resuelta', $emergencia->estado);
        $this->assertSame(25, $emergencia->minutosRespuesta());
        $this->assertEquals(2380000, (float) $equipo->cotizaciones()->value('total'));

        $exportado = $this->get('/api/v1/excel/exportar/servicio')->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'servicio').'.xlsx';
        file_put_contents($ruta, $exportado->streamedContent());
        $libro = IOFactory::load($ruta);
        $this->assertSame('ASC-T1', $libro->getSheetByName('Certificaciones')->getCell('A2')->getValue());
        $this->assertSame('Personas atrapadas', $libro->getSheetByName('Emergencias')->getCell('C2')->getValue());
        $this->assertSame('Cambio de amortiguadores', $libro->getSheetByName('Cotizaciones')->getCell('C2')->getValue());

        // Reimportar lo exportado no duplica nada.
        $vista = $this->post('/api/v1/excel/importaciones', ['archivo' => new UploadedFile($ruta, 'servicio.xlsx', null, null, true)], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertSame(0, $vista->json('datos.totales.errores'));
        foreach (['Certificaciones', 'Emergencias', 'Cotizaciones'] as $hoja) {
            $resumen = collect($vista->json('datos.hojas'))->firstWhere('nombre', $hoja);
            $this->assertSame(0, $resumen['creados'], "La hoja {$hoja} no debe crear registros nuevos al reimportar.");
            $this->assertSame(1, $resumen['existentes']);
        }
    }

    public function test_exporta_solo_la_seccion_que_se_esta_viendo(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $cliente = Cliente::create(['tipo' => 'juridica', 'nombre' => 'Mixto', 'nit' => '900777888-1', 'estado' => true]);
        foreach (['conjunto' => 'ASC-CON', 'institucional' => 'ASC-INS'] as $tipo => $codigo) {
            $edificio = $cliente->edificios()->create([
                'tipo' => $tipo, 'nombre' => "Edificio {$tipo}", 'direccion' => 'Calle 1', 'ciudad' => 'Bogotá',
                'nombre_contacto' => 'Ana', 'telefono_contacto' => '3001112233',
            ]);
            $edificio->equipos()->create(['codigo' => $codigo, 'tipo' => 'ascensor', 'estado' => 'activo']);
        }

        $respuesta = $this->get('/api/v1/excel/exportar/ascensores?tipo_edificio=conjunto')->assertOk();
        $this->assertStringContainsString('SIGMA_Ascensores_Conjuntos_', $respuesta->headers->get('content-disposition'));
        $ruta = tempnam(sys_get_temp_dir(), 'seccion').'.xlsx';
        file_put_contents($ruta, $respuesta->streamedContent());
        $hoja = IOFactory::load($ruta)->getSheetByName('Ascensores');

        $this->assertSame('ASC-CON', $hoja->getCell('A2')->getValue());
        $this->assertNull($hoja->getCell('A3')->getValue(), 'El ascensor del edificio institucional no debe salir.');
    }

    public function test_exportar_e_importar_de_nuevo_no_cambia_ni_pierde_datos(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $id = $this->post('/api/v1/excel/importaciones', ['archivo' => $this->archivo($this->datosBasicos())], ['Accept' => 'application/json'])->json('datos.id');
        $this->postJson("/api/v1/excel/importaciones/{$id}/confirmar")->assertOk();
        $antes = Equipo::with('ascensor')->where('codigo', 'ASC-T1')->firstOrFail();

        $exportado = $this->get('/api/v1/excel/exportar/todo')->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
        file_put_contents($ruta, $exportado->streamedContent());

        $vista = $this->post('/api/v1/excel/importaciones', ['archivo' => new UploadedFile($ruta, 'export.xlsx', null, null, true)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('datos.totales.errores', 0)
            ->assertJsonPath('datos.totales.duplicados', 0)
            ->assertJsonPath('datos.derivados.contratos_creados', 0);
        $hojas = collect($vista->json('datos.hojas'))->keyBy('nombre');
        $this->assertSame(0, $hojas['Ascensores']['creados']);
        $this->assertSame(1, $hojas['Facturas']['existentes']);

        $this->postJson('/api/v1/excel/importaciones/'.$vista->json('datos.id').'/confirmar')->assertOk();
        $despues = Equipo::with('ascensor')->where('codigo', 'ASC-T1')->firstOrFail();
        $this->assertSame($antes->fecha_instalacion?->toDateString(), $despues->fecha_instalacion?->toDateString());
        $this->assertEquals((float) $antes->ascensor->velocidad_mpm, (float) $despues->ascensor->velocidad_mpm);
        $this->assertSame(7, $despues->puertasPiso()->count());
        $this->assertSame(1, Factura::count());
    }

    public function test_los_errores_se_descargan_con_una_columna_de_observaciones(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $id = $this->post('/api/v1/excel/importaciones', ['archivo' => $this->archivo($this->datosBasicos())], ['Accept' => 'application/json'])->json('datos.id');

        $descarga = $this->get("/api/v1/excel/importaciones/{$id}/errores")->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'errores').'.xlsx';
        file_put_contents($ruta, $descarga->streamedContent());
        $hoja = IOFactory::load($ruta)->getSheetByName('Ascensores');
        $columna = Plantilla::letra(count(Plantilla::hoja('ascensores')['columnas']) + 1);

        $this->assertSame('Observaciones de SIGMA', $hoja->getCell("{$columna}1")->getValue());
        $this->assertStringStartsWith('No se cargó:', (string) $hoja->getCell("{$columna}4")->getValue());
        $this->assertNull($hoja->getCell("{$columna}3")->getValue());
    }

    public function test_importar_exige_permiso_y_respeta_los_permisos_de_cada_hoja(): void
    {
        $this->actingAs($this->usuario('supervisor'), 'sanctum')
            ->post('/api/v1/excel/importaciones', ['archivo' => $this->archivo($this->datosBasicos())], ['Accept' => 'application/json'])
            ->assertForbidden();

        // El contador importa cartera, pero no puede crear clientes.
        $vista = $this->actingAs($this->usuario('contador'), 'sanctum')
            ->post('/api/v1/excel/importaciones', ['archivo' => $this->archivo(['clientes' => $this->datosBasicos()['clientes']])], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertStringContainsString('Su rol no tiene permiso para crear registros de la hoja Clientes', $vista->json('datos.errores.0.motivo'));
    }

    public function test_un_csv_se_reconoce_por_sus_encabezados(): void
    {
        $this->actingAs($this->administrador, 'sanctum');
        $csv = "Código *;Nombre o razón social *;Tipo de persona *;NIT o documento *\nCL-C01;Edificio Central;Persona jurídica;901222333-4\n";
        $ruta = tempnam(sys_get_temp_dir(), 'csv').'.csv';
        file_put_contents($ruta, mb_convert_encoding($csv, 'Windows-1252', 'UTF-8'));

        $id = $this->post('/api/v1/excel/importaciones', ['archivo' => new UploadedFile($ruta, 'clientes.csv', 'text/csv', null, true)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('datos.hojas.0.nombre', 'Clientes')
            ->assertJsonPath('datos.totales.validas', 1)
            ->json('datos.id');
        $this->postJson("/api/v1/excel/importaciones/{$id}/confirmar")->assertOk();
        $this->assertDatabaseHas('clientes', ['codigo' => 'CL-C01', 'nit' => '901222333-4']);
    }
}
