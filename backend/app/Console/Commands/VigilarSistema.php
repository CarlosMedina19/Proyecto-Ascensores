<?php

namespace App\Console\Commands;

use App\Notifications\AlertaOperacionNotification;
use App\Services\NotificacionService;
use App\Support\Archivos;
use App\Support\Vigilancia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Vigilancia de la operación (cada 5 minutos). Revisa lo que falla en silencio y avisa a los
 * administradores en la campana del panel y en el registro de errores, una vez por hora y por problema:
 *
 * - Trabajos de la cola que fallaron en la última hora.
 * - Cola atascada: trabajos esperando más de 10 minutos (el procesador de la cola no corre).
 * - Analítica (Python) y Object Storage sin respuesta.
 * - Respaldo remoto con más de 30 horas de antigüedad o con error.
 * - Disco del servidor con menos del 10 % libre.
 */
class VigilarSistema extends Command
{
    protected $signature = 'sigma:vigilar {--sin-avisos : Solo muestra el resultado}';

    protected $description = 'Revisa cola, servicios, respaldos y disco, y avisa a los administradores si algo falla';

    public function handle(NotificacionService $notificaciones): int
    {
        $problemas = array_filter([
            'cola_fallida' => $this->trabajosFallidos(),
            'cola_atascada' => $this->colaAtascada(),
            'analitica' => $this->analitica(),
            'almacenamiento' => $this->almacenamiento(),
            'respaldo_remoto' => $this->respaldoRemoto(),
            'disco' => $this->disco(),
        ]);

        foreach ($problemas as $clave => [$titulo, $detalle]) {
            $this->warn("{$titulo}: {$detalle}");
            Log::critical("Vigilancia: {$titulo}", ['detalle' => $detalle]);
            // Un aviso por problema y por hora; si sigue, vuelve a avisar en la hora siguiente.
            if (! $this->option('sin-avisos') && Cache::add("vigilancia:aviso:{$clave}", true, now()->addHour())) {
                try {
                    $notificaciones->enviar(
                        $notificaciones->administradores(),
                        new AlertaOperacionNotification($titulo, $detalle),
                        "alerta_operacion_{$clave}_".now()->format('YmdH'),
                        'sistema',
                        0,
                    );
                } catch (Throwable $e) {
                    Log::error('Vigilancia: no se pudo avisar a los administradores', ['error' => $e->getMessage()]);
                }
            }
        }

        if (! $problemas) {
            $this->info('Todo en orden.');
        }

        return $problemas ? self::FAILURE : self::SUCCESS;
    }

    private function trabajosFallidos(): ?array
    {
        $cantidad = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();

        return $cantidad > 0 ? ['Trabajos fallidos', "{$cantidad} trabajo(s) de la cola fallaron en la última hora (avisos, correos o tiempo real). Revise el registro del servidor."] : null;
    }

    private function colaAtascada(): ?array
    {
        if (! config('sigma.vigilancia.revisar_cola', config('queue.default') === 'database')) {
            return null;
        }
        $masViejo = DB::table('jobs')->whereNull('reserved_at')->min('available_at');
        $minutos = $masViejo ? (int) floor((now()->timestamp - (int) $masViejo) / 60) : 0;

        return $minutos >= 10 ? ['Cola detenida', "Hay trabajos esperando desde hace {$minutos} minutos: el procesador de la cola (sigma_queue) no está corriendo."] : null;
    }

    private function analitica(): ?array
    {
        $url = rtrim((string) config('services.analitica.url'), '/');
        if ($url === '' || blank(config('services.analitica.clave'))) {
            return null;
        }
        try {
            $ok = Http::timeout(5)->get("{$url}/salud")->successful();
        } catch (Throwable) {
            $ok = false;
        }

        return $ok ? null : ['Analítica sin respuesta', 'El servicio de analítica (sigma_analitica) no responde. La sección «Analítica avanzada» de Indicadores no se puede calcular.'];
    }

    private function almacenamiento(): ?array
    {
        try {
            $disco = Archivos::disco();
            $disco->put('salud/latido.txt', now()->toIso8601String());
            $ok = $disco->exists('salud/latido.txt');
        } catch (Throwable $e) {
            $ok = false;
            Log::error('Vigilancia: almacenamiento de archivos', ['error' => $e->getMessage()]);
        }

        return $ok ? null : ['Almacenamiento sin respuesta', 'No se pudo escribir en el almacenamiento de archivos ('.Archivos::nombreDisco().'): fotos, firmas y PDF no se pueden guardar.'];
    }

    private function respaldoRemoto(): ?array
    {
        if (! config('sigma.respaldos.vigilar')) {
            return null;
        }
        $fallo = Vigilancia::ultimoFallo('respaldo_remoto');
        if ($fallo) {
            return ['Respaldo remoto con error', $fallo['detalle']];
        }
        $ultimo = Vigilancia::ultimoExito('respaldo_remoto');
        if (! $ultimo || $ultimo->lt(now()->subHours(30))) {
            $cuando = $ultimo ? 'desde el '.$ultimo->timezone(config('app.timezone'))->format('d/m/Y H:i') : 'nunca';

            return ['Respaldo remoto atrasado', "No hay respaldo de la base fuera del servidor {$cuando}. Revise la tarea sigma:respaldar-remoto."];
        }

        return null;
    }

    private function disco(): ?array
    {
        $libre = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        if (! $libre || ! $total) {
            return null;
        }
        $porcentaje = $libre / $total * 100;

        return $porcentaje < (float) config('sigma.vigilancia.disco_minimo', 10) ? ['Disco casi lleno', sprintf('Queda %.1f %% libre en el disco del servidor (%.1f GB). Afecta a todos los proyectos del servidor.', $porcentaje, $libre / 1073741824)] : null;
    }
}
