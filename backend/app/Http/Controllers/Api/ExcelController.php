<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Excel\ExportacionService;
use App\Services\Excel\ImportacionService;
use App\Support\Excel\LectorSigma;
use App\Support\Excel\Plantilla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Plantilla, exportación e importación de datos en Excel.
 * La importación tiene dos pasos: vista previa (no guarda) y confirmación (guarda solo las filas válidas).
 */
class ExcelController extends Controller
{
    private const CARPETA = 'importaciones';

    public function __construct(
        private readonly ImportacionService $importacion,
        private readonly ExportacionService $exportacion,
    ) {}

    public function plantilla(): BinaryFileResponse
    {
        return response()->download(Plantilla::rutaArchivo(), Plantilla::estructura()['nombre']);
    }

    public function exportar(Request $solicitud, string $modulo): BinaryFileResponse
    {
        abort_unless(array_key_exists($modulo, ExportacionService::MODULOS), 404, 'Ese módulo no se puede exportar.');
        $ruta = $this->exportacion->exportar($modulo, $solicitud->user());

        return response()->download($ruta, ExportacionService::nombreArchivo($modulo))->deleteFileAfterSend();
    }

    /** Paso 1: sube el archivo y devuelve la vista previa sin guardar nada. */
    public function previsualizar(Request $solicitud): JsonResponse
    {
        abort_unless($solicitud->user()->tienePermiso('datos.importar'), 403, 'Su rol no tiene permiso para importar datos.');
        $solicitud->validate(
            ['archivo' => ['required', 'file', 'max:10240', 'mimes:xlsx,csv,txt']],
            ['archivo.mimes' => 'Suba un archivo de Excel (.xlsx) o CSV.', 'archivo.max' => 'El archivo puede pesar máximo 10 MB.'],
            ['archivo' => 'archivo'],
        );

        $archivo = $solicitud->file('archivo');
        $extension = Str::lower($archivo->getClientOriginalExtension() ?: $archivo->extension());
        $id = (string) Str::uuid();
        $ruta = $archivo->storeAs(self::CARPETA, "{$id}.{$extension}", 'local');

        $lectura = LectorSigma::leer(Storage::disk('local')->path($ruta), $extension);
        $resultado = $this->importacion->procesar($lectura, $solicitud->user(), false);

        Storage::disk('local')->put(self::CARPETA."/{$id}.json", json_encode([
            'usuario_id' => $solicitud->user()->id,
            'nombre' => $archivo->getClientOriginalName(),
            'extension' => $extension,
            'resultado' => $resultado,
        ], JSON_UNESCAPED_UNICODE));

        return response()->json(['datos' => ['id' => $id, 'archivo' => $archivo->getClientOriginalName()] + $this->publico($resultado)]);
    }

    /** Paso 2: importa solo las filas válidas del archivo de la vista previa. */
    public function confirmar(Request $solicitud, string $id): JsonResponse
    {
        abort_unless($solicitud->user()->tienePermiso('datos.importar'), 403, 'Su rol no tiene permiso para importar datos.');
        $meta = $this->meta($solicitud, $id);
        $lectura = LectorSigma::leer($this->rutaArchivo($id, $meta), $meta['extension']);
        $resultado = $this->importacion->procesar($lectura, $solicitud->user(), true);
        $meta['resultado'] = $resultado;
        $meta['confirmada'] = true;
        Storage::disk('local')->put(self::CARPETA."/{$id}.json", json_encode($meta, JSON_UNESCAPED_UNICODE));

        return response()->json(['datos' => ['id' => $id, 'mensaje' => $this->mensajeFinal($resultado)] + $this->publico($resultado)]);
    }

    /** El archivo subido, con una columna de observaciones en cada fila que no se cargó. */
    public function errores(Request $solicitud, string $id): BinaryFileResponse
    {
        $meta = $this->meta($solicitud, $id);
        if ($meta['extension'] === 'xlsx') {
            $libro = IOFactory::load($this->rutaArchivo($id, $meta));
        } else {
            $lector = new Csv;
            $libro = $lector->load($this->rutaArchivo($id, $meta));
            $clave = array_key_first($meta['resultado']['hojas']);
            $libro->getActiveSheet()->setTitle($clave ? Plantilla::hoja($clave)['nombre'] : 'Datos');
        }

        foreach ($meta['resultado']['hojas'] as $clave => $resumen) {
            $hoja = $libro->getSheetByName($resumen['nombre']);
            if (! $hoja) {
                continue;
            }
            $columna = Plantilla::letra(count(Plantilla::hoja($clave)['columnas']) + 1);
            $hoja->setCellValue("{$columna}1", 'Observaciones de SIGMA');
            $hoja->getStyle("{$columna}1")->applyFromArray(['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFC2362B']]]);
            $hoja->getColumnDimension($columna)->setWidth(70);
            foreach ($resumen['filas'] as $fila => $estado) {
                if (! in_array($estado['estado'], ['error', 'duplicado'], true)) {
                    continue;
                }
                $hoja->setCellValue("{$columna}{$fila}", ($estado['estado'] === 'duplicado' ? 'Duplicado: ' : 'No se cargó: ').$estado['motivo']);
                $hoja->getStyle("A{$fila}:{$columna}{$fila}")->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFBE7E5');
                $hoja->getStyle("{$columna}{$fila}")->getAlignment()->setWrapText(true);
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'sigma').'.xlsx';
        IOFactory::createWriter($libro, 'Xlsx')->save($ruta);
        $nombre = pathinfo($meta['nombre'], PATHINFO_FILENAME).'_errores.xlsx';

        return response()->download($ruta, $nombre)->deleteFileAfterSend();
    }

    private function meta(Request $solicitud, string $id): array
    {
        abort_unless(Str::isUuid($id) && Storage::disk('local')->exists(self::CARPETA."/{$id}.json"), 404, 'La importación ya no está disponible. Vuelva a subir el archivo.');
        $meta = json_decode(Storage::disk('local')->get(self::CARPETA."/{$id}.json"), true);
        abort_unless($meta['usuario_id'] === $solicitud->user()->id, 403, 'Esta importación pertenece a otro usuario.');

        return $meta;
    }

    private function rutaArchivo(string $id, array $meta): string
    {
        return Storage::disk('local')->path(self::CARPETA."/{$id}.{$meta['extension']}");
    }

    /** Resumen sin el detalle interno por fila. */
    private function publico(array $resultado): array
    {
        $hojas = array_map(fn ($h) => array_diff_key($h, ['filas' => true]), $resultado['hojas']);
        $validas = array_sum(array_map(fn ($h) => $h['creados'] + $h['actualizados'] + $h['existentes'], $hojas));
        $conError = array_sum(array_column($hojas, 'errores'));
        $duplicadas = array_sum(array_column($hojas, 'duplicados'));

        return [
            'hojas' => array_values($hojas),
            'totales' => ['validas' => $validas, 'errores' => $conError, 'duplicados' => $duplicadas],
            'errores' => $resultado['errores'],
            'duplicados' => $resultado['duplicados'],
            'derivados' => $resultado['derivados'],
            'avisos' => $resultado['avisos'],
        ];
    }

    /** "Se importaron 42 ascensores y 318 puertas de piso. 3 filas no se cargaron." */
    private function mensajeFinal(array $resultado): string
    {
        $etiquetas = [
            'clientes' => ['cliente', 'clientes'], 'edificios' => ['edificio', 'edificios'], 'ascensores' => ['ascensor', 'ascensores'],
            'puertas' => ['puerta de piso', 'puertas de piso'], 'ordenes' => ['orden de trabajo', 'órdenes de trabajo'],
            'checklist' => ['ítem del checklist', 'ítems del checklist'], 'facturas' => ['factura', 'facturas'],
            'certificaciones' => ['certificación', 'certificaciones'], 'emergencias' => ['emergencia', 'emergencias'],
            'cotizaciones' => ['cotización', 'cotizaciones'],
            'pagos' => ['pago', 'pagos'], 'usuarios' => ['usuario', 'usuarios'],
        ];
        $partes = [];
        $actualizados = 0;
        foreach ($resultado['hojas'] as $clave => $h) {
            $actualizados += $h['actualizados'];
            $partes[] = $this->cantidad($h['creados'], $etiquetas[$clave]);
            // Las puertas de piso que SIGMA crea con cada ascensor nuevo.
            if ($clave === 'ascensores' && $resultado['derivados']['puertas_creadas'] > 0) {
                $partes[] = $this->cantidad($resultado['derivados']['puertas_creadas'], $etiquetas['puertas']);
            }
        }
        $partes[] = $this->cantidad($resultado['derivados']['contratos_creados'], ['contrato', 'contratos']);
        $partes = array_values(array_filter($partes));

        $frases = [];
        if ($partes) {
            $frases[] = 'Se importaron '.$this->unir($partes).'.';
        }
        if ($actualizados > 0) {
            $frases[] = $actualizados === 1 ? 'Se actualizó 1 registro existente.' : "Se actualizaron {$actualizados} registros existentes.";
        }
        if ($frases === []) {
            $frases[] = 'No había registros nuevos ni cambios para importar.';
        }
        $noCargadas = array_sum(array_column($resultado['hojas'], 'errores')) + array_sum(array_column($resultado['hojas'], 'duplicados'));
        if ($noCargadas > 0) {
            $frases[] = $noCargadas === 1 ? '1 fila no se cargó.' : "{$noCargadas} filas no se cargaron.";
        }
        $texto = implode(' ', $frases);

        return $texto;
    }

    private function cantidad(int $n, array $etiqueta): ?string
    {
        return $n > 0 ? $n.' '.($n === 1 ? $etiqueta[0] : $etiqueta[1]) : null;
    }

    private function unir(array $partes): string
    {
        return count($partes) === 1 ? $partes[0] : implode(', ', array_slice($partes, 0, -1)).' y '.end($partes);
    }
}
