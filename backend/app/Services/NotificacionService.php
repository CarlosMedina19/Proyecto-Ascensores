<?php

namespace App\Services;

use App\Jobs\EnviarNotificacionJob;
use App\Models\Configuracion;
use App\Models\EnvioNotificacion;
use App\Models\Equipo;
use App\Models\MantenimientoProgramado;
use App\Models\OrdenTrabajo;
use App\Models\Usuario;
use App\Notifications\AvisoMantenimientoNotification;
use App\Notifications\NotificacionSigma;
use App\Notifications\OrdenTrabajoNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * RF-021: decide a quién avisar y por qué canales, registra cada envío y evita duplicados.
 */
class NotificacionService
{
    /** Canales activos configurados por el administrador. */
    public function canales(): array
    {
        return array_values(array_intersect((array) Configuracion::valor('notificaciones.canales'), ['database', 'mail']));
    }

    /**
     * Registra y encola el aviso para cada usuario y canal. Un aviso ya enviado no se repite.
     *
     * @param  iterable<Usuario>  $usuarios
     * @return int envíos encolados
     */
    public function enviar(iterable $usuarios, NotificacionSigma $notificacion, string $tipo, string $referenciaTipo, int $referenciaId): int
    {
        $encolados = 0;

        foreach ($usuarios as $usuario) {
            foreach ($this->canales() as $canal) {
                $clave = [
                    'tipo' => $tipo,
                    'referencia_tipo' => $referenciaTipo,
                    'referencia_id' => $referenciaId,
                    'usuario_id' => $usuario->id,
                    'canal' => $canal,
                ];

                try {
                    $envio = EnvioNotificacion::firstOrCreate($clave, ['estado' => 'pendiente']);
                } catch (UniqueConstraintViolationException) {
                    continue; // Otro proceso registró el mismo aviso al mismo tiempo.
                }

                if ($envio->estado === 'enviado' || ! $envio->wasRecentlyCreated && $envio->estado === 'pendiente') {
                    continue;
                }

                if ($envio->estado === 'fallido') {
                    $envio->update(['estado' => 'pendiente', 'error' => null]);
                }

                EnviarNotificacionJob::dispatch($envio->id, $notificacion);
                $encolados++;
            }
        }

        return $encolados;
    }

    /**
     * Destinatarios de los avisos de un equipo: usuarios del cliente dueño del equipo
     * y coordinadores. (Los técnicos asignados se agregan con las órdenes de la fase 3.)
     *
     * @return Collection<int, Usuario>
     */
    public function destinatariosEquipo(Equipo $equipo): Collection
    {
        $clienteId = $equipo->edificio?->cliente_id;

        return Usuario::query()
            ->where('activo', true)
            ->where(function ($consulta) use ($clienteId) {
                $consulta->whereHas('rol', fn ($rol) => $rol->where('nombre', 'coordinador'))
                    ->orWhere(fn ($cliente) => $cliente
                        ->whereHas('rol', fn ($rol) => $rol->where('nombre', 'cliente'))
                        ->where('cliente_id', $clienteId));
            })
            ->get();
    }

    /** Personal que recibe las alertas de contratos: administrador, coordinador y contador. */
    public function destinatariosContratos(): Collection
    {
        return Usuario::query()
            ->where('activo', true)
            ->whereHas('rol', fn ($rol) => $rol->whereIn('nombre', ['administrador', 'coordinador', 'contador']))
            ->get();
    }

    /** RF-027: coordinadores activos (reciben las órdenes con prioridad crítica). */
    public function coordinadores(): Collection
    {
        return Usuario::query()
            ->where('activo', true)
            ->whereHas('rol', fn ($rol) => $rol->where('nombre', 'coordinador'))
            ->get();
    }

    /**
     * Aviso de una orden de trabajo a los usuarios indicados (técnicos o coordinadores).
     *
     * @param  iterable<Usuario>  $usuarios
     */
    public function eventoOrden(OrdenTrabajo $orden, string $evento, iterable $usuarios): int
    {
        $orden->loadMissing(['equipo', 'edificio']);

        return $this->enviar(
            $usuarios,
            new OrdenTrabajoNotification($orden, $evento),
            "orden_{$evento}_".now()->format('YmdHisv'),
            'orden',
            $orden->id,
        );
    }

    /** Aviso por un evento posterior del mantenimiento (reprogramado, cancelado, ejecutado). */
    public function eventoMantenimiento(MantenimientoProgramado $mantenimiento, string $evento): int
    {
        $mantenimiento->loadMissing('equipo.edificio');
        $tipo = "mantenimiento_{$evento}_".now()->format('YmdHis');

        return $this->enviar(
            $this->destinatariosEquipo($mantenimiento->equipo),
            new AvisoMantenimientoNotification($mantenimiento, $evento),
            $tipo,
            'mantenimiento',
            $mantenimiento->id,
        );
    }
}
