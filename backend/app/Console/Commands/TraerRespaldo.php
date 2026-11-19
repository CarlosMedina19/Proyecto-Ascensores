<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Recuperación ante desastres: descarga un respaldo guardado fuera del servidor (Object Storage, «respaldos/»)
 * a storage/app/private/respaldos-descargados, para restaurarlo con pg_restore. Sirve aunque la VPS
 * original ya no exista: basta un servidor nuevo con el código y el .env.
 */
class TraerRespaldo extends Command
{
    protected $signature = 'sigma:traer-respaldo {nombre? : Archivo exacto; por defecto el volcado de base más reciente} {--listar : Solo lista los respaldos remotos}';

    protected $description = 'Descarga un respaldo del Object Storage para restaurarlo';

    public function handle(): int
    {
        $remoto = Storage::disk((string) config('sigma.respaldos.disco_remoto'));
        $objetos = collect($remoto->files('respaldos'))->map(fn ($o) => basename($o))->sort()->values();

        if ($this->option('listar')) {
            $objetos->each(fn ($o) => $this->line($o.'  '.number_format($remoto->size("respaldos/{$o}") / 1024, 0).' KB'));

            return self::SUCCESS;
        }

        $nombre = $this->argument('nombre') ?: $objetos->filter(fn ($o) => str_starts_with($o, 'sigma_db_'))->last();
        if (! $nombre || ! $objetos->contains($nombre)) {
            $this->error('No hay ese respaldo en el Object Storage. Use --listar para ver los disponibles.');

            return self::FAILURE;
        }

        $destino = "respaldos-descargados/{$nombre}";
        Storage::disk('local')->writeStream($destino, $remoto->readStream("respaldos/{$nombre}"));
        $this->info('Descargado: '.Storage::disk('local')->path($destino));
        $this->line('Siga «Restaurar la base» en backend/README.md (sección «Operación y emergencias»).');

        return self::SUCCESS;
    }
}
