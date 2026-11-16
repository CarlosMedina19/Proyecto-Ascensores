<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Copia los archivos guardados en el disco local al Object Storage de SIGMA.
 *
 * No es destructivo: no borra los archivos locales ni cambia la base de datos (la clave del objeto es la
 * misma ruta relativa). Es idempotente: si el objeto ya existe con el mismo tamaño, lo omite.
 * Deja un reporte JSON en storage/app/migracion-archivos/.
 */
class MigrarArchivosObjetos extends Command
{
    protected $signature = 'sigma:migrar-archivos {--origen=local : Disco de origen} {--destino=objetos : Disco de destino} {--simular : Solo muestra lo que haría}';

    protected $description = 'Copia fotos, firmas, certificados e informes al Object Storage sin borrar los originales';

    /** Tablas y columnas que guardan rutas de archivos. */
    private const COLUMNAS = [
        ['fotos_equipo', 'ruta'],
        ['planilla_fotos', 'ruta'],
        ['firmas_orden', 'ruta'],
        ['informes_orden', 'ruta'],
        ['certificaciones', 'archivo_ruta'],
        ['visitas_tecnicas', 'firma_tecnico_ruta'],
        ['visitas_tecnicas', 'firma_recibe_ruta'],
        ['visitas_tecnicas', 'pdf_ruta'],
    ];

    public function handle(): int
    {
        $origen = Storage::disk($this->option('origen'));
        $destino = Storage::disk($this->option('destino'));
        $simular = (bool) $this->option('simular');
        $reporte = ['inicio' => now()->toIso8601String(), 'simulacion' => $simular, 'copiados' => [], 'omitidos' => [], 'faltantes' => [], 'fallidos' => []];

        $rutas = collect(self::COLUMNAS)
            ->flatMap(fn ($c) => DB::getSchemaBuilder()->hasTable($c[0]) ? DB::table($c[0])->whereNotNull($c[1])->pluck($c[1]) : [])
            ->filter()
            ->unique()
            ->values();
        $this->info("Archivos registrados: {$rutas->count()}");

        foreach ($rutas as $ruta) {
            try {
                if (! $origen->exists($ruta)) {
                    $reporte['faltantes'][] = $ruta;

                    continue;
                }
                $tamano = $origen->size($ruta);
                if ($destino->exists($ruta) && $destino->size($ruta) === $tamano) {
                    $reporte['omitidos'][] = $ruta;

                    continue;
                }
                if (! $simular) {
                    $flujo = $origen->readStream($ruta);
                    $destino->writeStream($ruta, $flujo);
                    if (is_resource($flujo)) {
                        fclose($flujo);
                    }
                    // Verificación de integridad: el objeto debe quedar con el mismo tamaño.
                    if ($destino->size($ruta) !== $tamano) {
                        throw new \RuntimeException('El tamaño del objeto no coincide con el original.');
                    }
                }
                $reporte['copiados'][] = ['ruta' => $ruta, 'bytes' => $tamano];
            } catch (Throwable $e) {
                $reporte['fallidos'][] = ['ruta' => $ruta, 'error' => $e->getMessage()];
            }
        }

        $reporte['fin'] = now()->toIso8601String();
        $archivo = 'migracion-archivos/'.now()->format('Ymd_His').'.json';
        Storage::disk('local')->put($archivo, json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->table(['Copiados', 'Ya estaban', 'Sin archivo local', 'Fallidos'], [[count($reporte['copiados']), count($reporte['omitidos']), count($reporte['faltantes']), count($reporte['fallidos'])]]);
        $this->line("Reporte: storage/app/{$archivo}");

        return $reporte['fallidos'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
