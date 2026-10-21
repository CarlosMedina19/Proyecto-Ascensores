<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Configuracion;
use App\Models\Emergencia;
use App\Models\Equipo;
use App\Models\Tecnico;
use App\Models\Usuario;
use App\Notifications\EmergenciaNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Emergencias y atrapamientos 24/7: reporte (panel, app o QR sin sesión), tiempo objetivo de llegada,
 * seguimiento por estados y registro en el historial del ascensor.
 */
class EmergenciaService
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    public function listar(array $filtros, int $porPagina, ?Usuario $usuario): LengthAwarePaginator
    {
        return Emergencia::query()
            ->with(['equipo.edificio.cliente', 'tecnico.usuario'])
            ->when($usuario?->esCliente(), fn ($q) => $q->whereHas('equipo.edificio', fn ($e) => $e->where('cliente_id', $usuario->cliente_id)))
            ->when($usuario?->esTecnico() && ! $usuario->tienePermiso('emergencias.gestionar'), fn ($q) => $q->where(
                fn ($t) => $t->where('tecnico_id', $usuario->tecnico?->id)->orWhereIn('estado', ['reportada'])
            ))
            ->when(($filtros['estado'] ?? null) === 'abiertas', fn ($q) => $q->whereIn('estado', ['reportada', 'asignada', 'en_sitio']))
            ->when(isset($filtros['estado']) && $filtros['estado'] !== 'abiertas', fn ($q) => $q->where('estado', $filtros['estado']))
            ->when($filtros['equipo_id'] ?? null, fn ($q, $id) => $q->where('equipo_id', $id))
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['busqueda'] ?? null, fn ($q, $texto) => $q->where(fn ($b) => $b
                ->where('numero', 'ilike', "%{$texto}%")
                ->orWhereHas('equipo', fn ($e) => $e->where('codigo', 'ilike', "%{$texto}%")->orWhere('ubicacion', 'ilike', "%{$texto}%"))))
            ->orderByRaw("CASE WHEN estado IN ('reportada', 'asignada', 'en_sitio') THEN 0 ELSE 1 END")
            ->orderByDesc('personas_atrapadas')
            ->orderByDesc('reportada_en')
            ->paginate($porPagina, ['*'], 'pagina');
    }

    public function reportar(Equipo $equipo, array $datos, string $origen): Emergencia
    {
        $atrapadas = ($datos['tipo'] ?? null) === 'atrapamiento' || ! empty($datos['personas_atrapadas']);
        $minutos = (int) Configuracion::valor($atrapadas ? 'emergencias.minutos_atrapamiento' : 'emergencias.minutos_objetivo');

        $emergencia = DB::transaction(function () use ($equipo, $datos, $origen, $atrapadas, $minutos) {
            $emergencia = Emergencia::create([
                'numero' => $this->siguienteNumero(),
                'equipo_id' => $equipo->id,
                'tipo' => $datos['tipo'],
                'origen' => $origen,
                'personas_atrapadas' => $atrapadas,
                'reportante_nombre' => $datos['reportante_nombre'] ?? Auth::user()?->nombre,
                'reportante_telefono' => $datos['reportante_telefono'] ?? null,
                'descripcion' => $datos['descripcion'] ?? null,
                'estado' => 'reportada',
                'minutos_objetivo' => $minutos,
                'reportada_en' => now(),
                'registrada_por' => Auth::id(),
            ]);
            $emergencia->seguimientos()->create([
                'estado' => 'reportada',
                'nota' => ['panel' => 'Reporte desde el panel.', 'app' => 'Reporte desde la app.', 'qr' => 'Reporte desde el código QR de la cabina.'][$origen],
                'usuario_id' => Auth::id(),
            ]);
            $equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'emergencia_reportada',
                'descripcion' => "Emergencia {$emergencia->numero}: ".mb_strtolower(Emergencia::TIPOS[$emergencia->tipo]).'.',
            ]);

            if (! empty($datos['tecnico_id'])) {
                $this->aplicarEstado($emergencia, 'asignada', ['tecnico_id' => $datos['tecnico_id']]);
            }

            return $emergencia;
        });

        $emergencia->load('equipo.edificio');
        $this->notificaciones->enviar(
            $this->notificaciones->coordinadores()->merge($this->notificaciones->destinatariosEquipo($equipo))->unique('id'),
            new EmergenciaNotification($emergencia, 'reportada'),
            'emergencia_reportada',
            'emergencia',
            $emergencia->id,
        );

        return $emergencia;
    }

    /**
     * Avanza la emergencia: asignada (requiere técnico), en_sitio (marca la llegada), resuelta (requiere
     * la solución) o cancelada (requiere el motivo en la nota).
     */
    public function cambiarEstado(Emergencia $emergencia, string $estado, array $datos): Emergencia
    {
        if (! in_array($estado, Emergencia::TRANSICIONES[$emergencia->estado] ?? [], true)) {
            throw new ReglaNegocioException(sprintf(
                'Una emergencia en estado «%s» no puede pasar a «%s».',
                Emergencia::ESTADOS[$emergencia->estado],
                Emergencia::ESTADOS[$estado] ?? $estado,
            ));
        }

        DB::transaction(fn () => $this->aplicarEstado($emergencia, $estado, $datos));

        $emergencia->refresh()->load('equipo.edificio');
        if (in_array($estado, ['asignada', 'resuelta'], true)) {
            $destinatarios = $this->notificaciones->destinatariosEquipo($emergencia->equipo);
            if ($estado === 'asignada' && $emergencia->tecnico?->usuario) {
                $destinatarios = $destinatarios->push($emergencia->tecnico->usuario)->unique('id');
            }
            $this->notificaciones->enviar(
                $destinatarios,
                new EmergenciaNotification($emergencia, $estado),
                "emergencia_{$estado}_".now()->format('YmdHis'),
                'emergencia',
                $emergencia->id,
            );
        }

        return $emergencia;
    }

    private function aplicarEstado(Emergencia $emergencia, string $estado, array $datos): void
    {
        $cambios = ['estado' => $estado];
        $nota = $datos['nota'] ?? null;

        switch ($estado) {
            case 'asignada':
                $tecnico = Tecnico::with('usuario')->find($datos['tecnico_id'] ?? null);
                if (! $tecnico) {
                    throw new ReglaNegocioException('Elija el técnico que atenderá la emergencia.');
                }
                $cambios += ['tecnico_id' => $tecnico->id, 'asignada_en' => now()];
                $nota ??= "Asignada a {$tecnico->usuario?->nombre}.";
                break;
            case 'en_sitio':
                $cambios['llegada_en'] = now();
                if (! $emergencia->tecnico_id && Auth::user()?->tecnico) {
                    $cambios['tecnico_id'] = Auth::user()->tecnico->id;
                }
                $nota ??= 'El técnico llegó al sitio.';
                break;
            case 'resuelta':
                if (blank($datos['solucion'] ?? null)) {
                    throw new ReglaNegocioException('Describa qué se hizo para resolver la emergencia.');
                }
                $cambios += ['resuelta_en' => now(), 'solucion' => $datos['solucion']];
                // Si se resolvió sin marcar la llegada, la llegada es el cierre.
                $cambios['llegada_en'] = $emergencia->llegada_en ?? now();
                $nota ??= $datos['solucion'];
                break;
            case 'cancelada':
                if (blank($nota)) {
                    throw new ReglaNegocioException('Escriba el motivo de la cancelación.');
                }
                $cambios['resuelta_en'] = now();
                break;
        }

        $emergencia->update($cambios);
        $emergencia->seguimientos()->create(['estado' => $estado, 'nota' => $nota, 'usuario_id' => Auth::id()]);

        if (in_array($estado, ['resuelta', 'cancelada'], true)) {
            $emergencia->equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => "emergencia_{$estado}",
                'descripcion' => "Emergencia {$emergencia->numero} ".($estado === 'resuelta' ? 'resuelta: ' : 'cancelada: ').$nota,
            ]);
        }
        RegistroAuditoriaService::registrar("emergencia_{$estado}", $emergencia, $cambios, null, 'emergencias');
    }

    /**
     * Emergencia pasada cargada desde Excel: conserva sus fechas y no envía avisos.
     *
     * @param  array{tipo: string, reportada_en: string, llegada_en: ?string, resuelta_en: ?string, solucion: ?string, tecnico_id: ?int}  $datos
     */
    public function registrarHistorica(Equipo $equipo, array $datos): Emergencia
    {
        $reportada = CarbonImmutable::parse($datos['reportada_en']);
        $llegada = $datos['llegada_en'] ? CarbonImmutable::parse($datos['llegada_en']) : null;
        $resuelta = $datos['resuelta_en'] ? CarbonImmutable::parse($datos['resuelta_en']) : null;
        if ($reportada->isFuture()) {
            throw new ReglaNegocioException('La fecha del reporte no puede ser futura.');
        }
        if ($llegada && $llegada->lt($reportada)) {
            throw new ReglaNegocioException('La hora de llegada no puede ser anterior al reporte.');
        }
        if ($resuelta && $resuelta->lt($llegada ?? $reportada)) {
            throw new ReglaNegocioException('La solución no puede ser anterior al reporte ni a la llegada.');
        }
        if ($resuelta && blank($datos['solucion'] ?? null)) {
            throw new ReglaNegocioException('Escriba la solución: la emergencia tiene fecha de solución.');
        }
        $atrapadas = $datos['tipo'] === 'atrapamiento';
        $estado = $resuelta ? 'resuelta' : ($llegada ? 'en_sitio' : ($datos['tecnico_id'] ? 'asignada' : 'reportada'));

        $emergencia = Emergencia::create([
            'numero' => $this->siguienteNumero($reportada->year),
            'equipo_id' => $equipo->id,
            'tipo' => $datos['tipo'],
            'origen' => 'panel',
            'personas_atrapadas' => $atrapadas,
            'reportante_nombre' => $datos['reportante_nombre'] ?? null,
            'reportante_telefono' => $datos['reportante_telefono'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'estado' => $estado,
            'minutos_objetivo' => (int) Configuracion::valor($atrapadas ? 'emergencias.minutos_atrapamiento' : 'emergencias.minutos_objetivo'),
            'tecnico_id' => $datos['tecnico_id'] ?? null,
            'reportada_en' => $reportada,
            'asignada_en' => $datos['tecnico_id'] ? $reportada : null,
            'llegada_en' => $llegada ?? ($resuelta ? $resuelta : null),
            'resuelta_en' => $resuelta,
            'solucion' => $datos['solucion'] ?? null,
            'registrada_por' => Auth::id(),
        ]);
        $emergencia->seguimientos()->create(['estado' => $estado, 'nota' => 'Cargada desde Excel.', 'usuario_id' => Auth::id()]);
        $equipo->historial()->create([
            'usuario_id' => Auth::id(),
            'evento' => 'emergencia_registrada',
            'descripcion' => "Emergencia {$emergencia->numero} del {$reportada->format('d/m/Y')}: ".mb_strtolower(Emergencia::TIPOS[$emergencia->tipo]).'.',
        ]);

        return $emergencia;
    }

    /** Número consecutivo por año (EM-2026-00001). */
    private function siguienteNumero(?int $anio = null): string
    {
        $anio ??= now()->year;
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32("emergencias-{$anio}")]);
        }
        $ultimo = Emergencia::where('numero', 'like', "EM-{$anio}-%")->orderByDesc('numero')->value('numero');

        return sprintf('EM-%d-%05d', $anio, $ultimo ? ((int) substr($ultimo, -5)) + 1 : 1);
    }
}
