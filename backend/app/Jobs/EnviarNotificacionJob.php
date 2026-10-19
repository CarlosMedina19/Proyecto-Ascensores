<?php

namespace App\Jobs;

use App\Models\EnvioNotificacion;
use App\Notifications\NotificacionSigma;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Envía un aviso por un canal y registra el resultado (RF-021).
 */
class EnviarNotificacionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(
        public int $envioId,
        public NotificacionSigma $notificacion,
    ) {}

    public function handle(): void
    {
        $envio = EnvioNotificacion::with('usuario')->find($this->envioId);

        if (! $envio || $envio->estado === 'enviado' || ! $envio->usuario?->activo) {
            return;
        }

        $envio->usuario->notifyNow($this->notificacion->porCanal($envio->canal));
        $envio->update(['estado' => 'enviado', 'enviado_en' => now(), 'error' => null]);
    }

    public function failed(Throwable $error): void
    {
        EnvioNotificacion::whereKey($this->envioId)->update([
            'estado' => 'fallido',
            'error' => mb_substr($error->getMessage(), 0, 1000),
        ]);
    }
}
