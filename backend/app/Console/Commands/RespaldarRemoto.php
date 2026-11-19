<?php

namespace App\Console\Commands;

use App\Support\Vigilancia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Copia fuera del servidor los respaldos diarios (volcado de la base y archivos) al Object Storage de SIGMA,
 * bajo «respaldos/». Así una falla de la VPS no se lleva la base y sus respaldos al tiempo.
 *
 * - Sube solo lo que falta o quedó incompleto (compara el tamaño) y verifica cada objeto subido.
 * - Conserva los respaldos remotos de los últimos días configurados y borra los más viejos.
 * - Registra la hora del último respaldo remoto correcto: la vigilancia avisa si pasa más de un día sin él.
 */
class RespaldarRemoto extends Command
{
    protected $signature = 'sigma:respaldar-remoto {--dias= : Días de respaldos remotos a conservar}';

    protected $description = 'Sube los respaldos diarios al Object Storage (fuera del servidor) y aplica la retención';

    private const PATRON = '/^sigma_(db|archivos)_\d{8}_\d{4}\.(dump|tar\.gz)$/';

    public function handle(): int
    {
        $carpeta = (string) config('sigma.respaldos.carpeta');
        $disco = Storage::disk((string) config('sigma.respaldos.disco_remoto'));
        $dias = (int) ($this->option('dias') ?: config('sigma.respaldos.dias_remotos'));

        if (! is_dir($carpeta)) {
            $this->error("No existe la carpeta de respaldos: {$carpeta}");
            Vigilancia::registrarFallo('respaldo_remoto', "No existe la carpeta de respaldos {$carpeta}.");

            return self::FAILURE;
        }

        $locales = collect(scandir($carpeta) ?: [])->filter(fn ($n) => preg_match(self::PATRON, $n))->sort()->values();
        if ($locales->isEmpty()) {
            $this->warn('No hay respaldos locales para subir.');
            Vigilancia::registrarFallo('respaldo_remoto', 'No hay respaldos locales en el servidor.');

            return self::FAILURE;
        }

        $subidos = 0;
        $fallidos = [];
        foreach ($locales as $nombre) {
            $ruta = "{$carpeta}/{$nombre}";
            $clave = "respaldos/{$nombre}";
            $tamano = filesize($ruta);
            try {
                if ($disco->exists($clave) && $disco->size($clave) === $tamano) {
                    continue;
                }
                $flujo = fopen($ruta, 'rb');
                $disco->writeStream($clave, $flujo);
                if (is_resource($flujo)) {
                    fclose($flujo);
                }
                if ($disco->size($clave) !== $tamano) {
                    throw new \RuntimeException('el tamaño del objeto no coincide con el archivo local');
                }
                $subidos++;
                $this->line("Subido: {$nombre} ({$tamano} bytes)");
            } catch (Throwable $e) {
                $fallidos[] = "{$nombre}: {$e->getMessage()}";
            }
        }

        // Retención remota: se borran los respaldos con fecha anterior al límite.
        $limite = now()->subDays($dias)->format('Ymd');
        $borrados = 0;
        try {
            foreach ($disco->files('respaldos') as $objeto) {
                if (preg_match('/_(\d{8})_\d{4}\./', basename($objeto), $m) && $m[1] < $limite) {
                    $disco->delete($objeto);
                    $borrados++;
                }
            }
        } catch (Throwable $e) {
            $fallidos[] = "retención: {$e->getMessage()}";
        }

        if ($fallidos) {
            $detalle = implode(' · ', $fallidos);
            Log::error('Respaldo remoto con fallas', ['fallas' => $fallidos]);
            Vigilancia::registrarFallo('respaldo_remoto', "No se pudieron subir algunos respaldos: {$detalle}");
            $this->error($detalle);

            return self::FAILURE;
        }

        Vigilancia::registrarExito('respaldo_remoto');
        $this->info("Respaldo remoto al día: {$subidos} subido(s), {$borrados} antiguo(s) borrado(s), {$locales->count()} local(es).");

        return self::SUCCESS;
    }
}
