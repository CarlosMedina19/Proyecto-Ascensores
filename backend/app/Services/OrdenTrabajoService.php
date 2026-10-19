<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\MantenimientoProgramado;
use App\Models\OrdenTrabajo;
use App\Models\Tecnico;
use App\Models\TrazabilidadOrden;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * RF-024 a RF-029: órdenes de trabajo, asignación de técnicos, estados, prioridad,
 * tiempos y trazabilidad.
 */
class OrdenTrabajoService
{
    /**
     * RF-026: flujo de trabajo que avanza el técnico asignado.
     * programada → asignada se hace al asignar técnicos; la cancelación va aparte.
     */
    private const TRANSICIONES = [
        'asignada' => ['en_camino'],
        'en_camino' => ['en_proceso'],
        'en_proceso' => ['pendiente', 'finalizada'],
        'pendiente' => ['en_proceso'],
    ];

    public const NOMBRES_ESTADO = [
        'programada' => 'Programada',
        'asignada' => 'Asignada',
        'en_camino' => 'En camino',
        'en_proceso' => 'En proceso',
        'pendiente' => 'Pendiente',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada',
    ];

    public function __construct(
        private readonly TecnicoService $tecnicos,
        private readonly NotificacionService $notificaciones,
        private readonly MantenimientoService $mantenimientos,
        private readonly PlanillaService $planillas,
        private readonly InformeService $informes,
    ) {}

    /** Consulta base según quién pregunta (RF-023). */
    public function consultaVisible(?Usuario $usuario = null): Builder
    {
        $usuario ??= Auth::user();
        $consulta = OrdenTrabajo::query();

        if ($usuario instanceof Usuario && $usuario->esTecnico()) {
            $consulta->whereHas('tecnicos', fn ($t) => $t->where('usuario_id', $usuario->id));
        } elseif ($usuario instanceof Usuario && $usuario->esCliente()) {
            $consulta->where('cliente_id', $usuario->cliente_id);
        }

        return $consulta;
    }

    public function listar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $consulta = $this->filtrar($this->consultaVisible(), $filtros)
            ->with(['cliente', 'edificio', 'equipo', 'tecnicos.usuario']);

        return $consulta->orderByDesc('fecha')->orderByDesc('hora')->orderByDesc('id')->paginate($porPagina, ['*'], 'pagina');
    }

    /** RF-023: órdenes de un rango de fechas para la agenda (sin paginar). */
    public function agenda(string $desde, string $hasta, array $filtros = []): Collection
    {
        return $this->filtrar($this->consultaVisible(), $filtros)
            ->with(['edificio', 'equipo', 'tecnicos.usuario'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->when(empty($filtros['estado']), fn ($c) => $c->where('estado', '!=', 'cancelada'))
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();
    }

    private function filtrar(Builder $consulta, array $filtros): Builder
    {
        foreach (['servicio', 'prioridad', 'equipo_id', 'cliente_id', 'edificio_id'] as $campo) {
            if (! empty($filtros[$campo])) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if (! empty($filtros['estado'])) {
            $consulta->whereIn('estado', (array) $filtros['estado']);
        }
        if (! empty($filtros['activas'])) {
            $consulta->whereNotIn('estado', OrdenTrabajo::FINALES);
        }
        if (! empty($filtros['tecnico_id'])) {
            $consulta->whereHas('tecnicos', fn ($t) => $t->whereKey($filtros['tecnico_id']));
        }
        if (! empty($filtros['desde'])) {
            $consulta->whereDate('fecha', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $consulta->whereDate('fecha', '<=', $filtros['hasta']);
        }
        if (! empty($filtros['busqueda'])) {
            $como = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $texto = "%{$filtros['busqueda']}%";
            $consulta->where(fn ($c) => $c->where('numero', $como, $texto)
                ->orWhereHas('equipo', fn ($e) => $e->where('codigo', $como, $texto))
                ->orWhereHas('edificio', fn ($e) => $e->where('nombre', $como, $texto)));
        }

        return $consulta;
    }

    /**
     * RF-025: crea la orden con número consecutivo. Si trae técnicos queda asignada.
     */
    public function crear(array $datos): OrdenTrabajo
    {
        return DB::transaction(function () use ($datos) {
            $mantenimiento = null;
            if (! empty($datos['mantenimiento_id'])) {
                $mantenimiento = MantenimientoProgramado::lockForUpdate()->findOrFail($datos['mantenimiento_id']);
                $this->validarMantenimiento($mantenimiento);
                $datos['servicio'] = 'preventivo';
                $datos['equipo_id'] = $mantenimiento->equipo_id;
                $datos['fecha'] ??= $mantenimiento->fecha_programada->toDateString();
                $datos['hora'] ??= substr((string) ($mantenimiento->hora ?? '08:00'), 0, 5);
            }

            $equipo = Equipo::with('edificio.cliente')->findOrFail($datos['equipo_id']);
            $this->validarUbicacion($equipo, $datos);

            $inicio = CarbonImmutable::parse("{$datos['fecha']} {$datos['hora']}");
            if ($inicio->lt(now()->startOfDay())) {
                throw new ReglaNegocioException('No se puede crear una orden en una fecha pasada.');
            }

            $orden = OrdenTrabajo::create([
                'numero' => $this->siguienteNumero(),
                'servicio' => $datos['servicio'],
                'cliente_id' => $equipo->edificio->cliente_id,
                'edificio_id' => $equipo->edificio_id,
                'equipo_id' => $equipo->id,
                'mantenimiento_id' => $mantenimiento?->id,
                'fecha' => $datos['fecha'],
                'hora' => $datos['hora'],
                'duracion_estimada' => $datos['duracion_estimada'] ?? 90,
                'prioridad' => $datos['prioridad'] ?? 'media',
                'estado' => 'programada',
                'descripcion' => $datos['descripcion'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'creado_por' => Auth::id(),
                // RF-038: correctivo derivado de un ítem de la planilla.
                'orden_origen_id' => $datos['orden_origen_id'] ?? null,
                'resultado_origen_id' => $datos['resultado_origen_id'] ?? null,
            ]);

            $servicio = ['preventivo' => 'preventivo', 'correctivo' => 'correctivo', 'inspeccion' => 'inspección'][$orden->servicio];
            $prioridad = ['baja' => 'baja', 'media' => 'media', 'alta' => 'alta', 'critica' => 'crítica'][$orden->prioridad];
            $this->trazar($orden, 'creada', null, null, null, "Orden {$orden->numero} creada ({$servicio}, prioridad {$prioridad}).");
            RegistroAuditoriaService::registrar('creado', $orden, $orden->toArray());

            if (! empty($datos['tecnicos'])) {
                $this->asignar($orden, $datos['tecnicos']);
            }
            if ($orden->prioridad === 'critica') {
                $this->notificaciones->eventoOrden($orden, 'critica', $this->notificaciones->coordinadores());
            }

            return $this->detalle($orden);
        });
    }

    /**
     * RF-024: reemplaza los técnicos de la orden. Exige al menos uno, técnicos activos y
     * disponibles, sin cruces de horario. La orden programada pasa a asignada.
     *
     * @param  array<int, int>  $tecnicoIds
     */
    public function asignar(OrdenTrabajo $orden, array $tecnicoIds): OrdenTrabajo
    {
        $this->exigirNoFinal($orden);
        if (in_array($orden->estado, ['en_camino', 'en_proceso'], true)) {
            throw new ReglaNegocioException('No se cambian los técnicos mientras la orden está en camino o en proceso.');
        }

        $tecnicoIds = array_values(array_unique(array_map('intval', $tecnicoIds)));
        if (! $tecnicoIds) {
            throw new ReglaNegocioException('La orden debe tener al menos un técnico.');
        }

        return DB::transaction(function () use ($orden, $tecnicoIds) {
            $tecnicos = Tecnico::with('usuario')->whereKey($tecnicoIds)->lockForUpdate()->get();
            if ($tecnicos->count() !== count($tecnicoIds)) {
                throw new ReglaNegocioException('Uno de los técnicos no existe.');
            }

            $actuales = $orden->tecnicos()->pluck('tecnicos.id')->all();
            $nuevos = array_diff($tecnicoIds, $actuales);

            foreach ($tecnicos->whereIn('id', $nuevos) as $tecnico) {
                $this->validarTecnicoParaOrden($tecnico, $orden);
            }

            $orden->tecnicos()->sync(collect($tecnicoIds)->mapWithKeys(fn ($id) => [
                $id => in_array($id, $actuales, true) ? [] : ['asignado_por' => Auth::id(), 'asignado_en' => now()],
            ])->all());

            $nombres = fn (array $ids) => Tecnico::with('usuario')->whereKey($ids)->get()->map(fn ($t) => $t->usuario?->nombre)->implode(', ');
            $retirados = array_diff($actuales, $tecnicoIds);
            if ($nuevos) {
                $this->trazar($orden, 'tecnico_asignado', 'tecnicos', null, $nombres($nuevos));
            }
            if ($retirados) {
                $this->trazar($orden, 'tecnico_retirado', 'tecnicos', $nombres($retirados), null);
            }

            if ($orden->estado === 'programada') {
                $this->cambiar($orden, 'asignada');
            }

            // RF-024: al asignar la orden se notifica al técnico.
            if ($nuevos) {
                $this->notificaciones->eventoOrden($orden, 'asignada', $tecnicos->whereIn('id', $nuevos)->pluck('usuario')->filter());
            }

            return $this->detalle($orden);
        });
    }

    /** Cambia fecha, hora o duración; revisa que los técnicos no queden con cruces. */
    public function reprogramar(OrdenTrabajo $orden, array $datos, string $motivo): OrdenTrabajo
    {
        $this->exigirNoFinal($orden);
        if (in_array($orden->estado, ['en_camino', 'en_proceso'], true)) {
            throw new ReglaNegocioException('Una orden en camino o en proceso no se reprograma.');
        }

        return DB::transaction(function () use ($orden, $datos, $motivo) {
            $anterior = $orden->inicioProgramado()->format('Y-m-d H:i')." ({$orden->duracion_estimada} min)";
            $orden->fill(collect($datos)->only(['fecha', 'hora', 'duracion_estimada'])->all());

            if ($orden->inicioProgramado()->lt(now()->startOfDay())) {
                throw new ReglaNegocioException('No se puede reprogramar a una fecha pasada.');
            }
            foreach ($orden->tecnicos()->with('usuario')->get() as $tecnico) {
                if ($this->tecnicos->conflictos($tecnico, $orden->inicioProgramado(), $orden->finProgramado(), $orden->id)->isNotEmpty()) {
                    throw new ReglaNegocioException("{$tecnico->usuario?->nombre} tiene otra orden que se cruza con el nuevo horario.");
                }
            }

            $orden->save();
            $nuevo = $orden->inicioProgramado()->format('Y-m-d H:i')." ({$orden->duracion_estimada} min)";
            $this->trazar($orden, 'reprogramada', 'horario', $anterior, $nuevo, $motivo);
            RegistroAuditoriaService::registrar('reprogramado', $orden, ['anterior' => $anterior, 'nuevo' => $nuevo, 'motivo' => $motivo]);
            $this->notificaciones->eventoOrden($orden, 'reprogramada', $this->usuariosAsignados($orden));

            return $this->detalle($orden);
        });
    }

    /** RF-027: solo coordinador y administrador cambian la prioridad; crítica avisa al coordinador. */
    public function cambiarPrioridad(OrdenTrabajo $orden, string $prioridad, ?string $motivo): OrdenTrabajo
    {
        $this->exigirNoFinal($orden);
        if ($orden->prioridad === $prioridad) {
            throw new ReglaNegocioException('La orden ya tiene esa prioridad.');
        }

        return DB::transaction(function () use ($orden, $prioridad, $motivo) {
            $anterior = $orden->prioridad;
            $orden->update(['prioridad' => $prioridad]);
            $this->trazar($orden, 'prioridad_cambiada', 'prioridad', $anterior, $prioridad, $motivo);
            RegistroAuditoriaService::registrar('prioridad_cambiada', $orden, ['anterior' => $anterior, 'nuevo' => $prioridad, 'motivo' => $motivo]);

            if ($prioridad === 'critica') {
                $this->notificaciones->eventoOrden($orden, 'critica', $this->notificaciones->coordinadores());
            }

            return $this->detalle($orden);
        });
    }

    /**
     * RF-026 y RF-028: avanza el estado. En proceso registra la hora de inicio; finalizada
     * registra la hora final, calcula la duración y, si es preventiva, ejecuta la visita programada.
     */
    public function avanzar(OrdenTrabajo $orden, string $estado, array $datos = []): OrdenTrabajo
    {
        $permitidos = self::TRANSICIONES[$orden->estado] ?? [];
        if (! in_array($estado, $permitidos, true)) {
            $de = self::NOMBRES_ESTADO[$orden->estado] ?? $orden->estado;
            $a = self::NOMBRES_ESTADO[$estado] ?? $estado;
            throw new ReglaNegocioException("Una orden {$de} no puede pasar a {$a}.");
        }
        if ($estado === 'pendiente' && blank($datos['motivo'] ?? null)) {
            throw new ReglaNegocioException('Indique por qué la orden queda pendiente.');
        }
        if ($estado === 'finalizada' && blank($datos['informe'] ?? null)) {
            throw new ReglaNegocioException('Escriba el informe del trabajo realizado para finalizar la orden.');
        }
        if ($estado === 'finalizada') {
            $this->exigirListaParaFinalizar($orden);
        }

        return DB::transaction(function () use ($orden, $estado, $datos) {
            $cambios = [];
            if ($estado === 'en_proceso' && ! $orden->inicio_en) {
                $cambios['inicio_en'] = now();
            }
            if ($estado === 'finalizada') {
                $fin = now();
                $inicio = $orden->inicio_en ?? $fin;
                $cambios += [
                    'fin_en' => $fin,
                    'duracion_minutos' => max(0, (int) round($inicio->diffInSeconds($fin) / 60)),
                    'informe' => $datos['informe'],
                    'observaciones' => $datos['observaciones'] ?? $orden->observaciones,
                ];
            }
            if ($cambios) {
                $orden->update($cambios);
            }

            $this->cambiar($orden, $estado, $datos['motivo'] ?? null);

            if (isset($cambios['inicio_en'])) {
                $this->trazar($orden, 'inicio_registrado', 'inicio_en', null, $orden->inicio_en->toIso8601String());
            }
            if ($estado === 'finalizada') {
                $this->trazar($orden, 'fin_registrado', 'fin_en', null, $orden->fin_en->toIso8601String(), "Duración: {$orden->duracion_minutos} min.");
                $this->alFinalizar($orden);
            }

            return $this->detalle($orden);
        });
    }

    /** RF-026: solo en programada, asignada o pendiente, con motivo. */
    public function cancelar(OrdenTrabajo $orden, string $motivo): OrdenTrabajo
    {
        if (! in_array($orden->estado, OrdenTrabajo::CANCELABLES, true)) {
            throw new ReglaNegocioException('Solo se cancelan órdenes programadas, asignadas o pendientes.');
        }

        return DB::transaction(function () use ($orden, $motivo) {
            $orden->update(['motivo_cancelacion' => $motivo]);
            $this->cambiar($orden, 'cancelada', $motivo);
            RegistroAuditoriaService::registrar('cancelado', $orden, ['motivo' => $motivo]);
            $this->notificaciones->eventoOrden($orden, 'cancelada', $this->usuariosAsignados($orden));

            return $this->detalle($orden);
        });
    }

    public function detalle(OrdenTrabajo $orden): OrdenTrabajo
    {
        return $orden->fresh([
            'cliente', 'edificio', 'equipo.ascensor', 'mantenimiento', 'tecnicos.usuario', 'tecnicos.especialidades', 'creador',
            'trazabilidad' => fn ($c) => $c->with('usuario')->orderByDesc('created_at')->orderByDesc('id'),
            // Fase 4: planilla, firmas, informes y correctivos derivados.
            'planilla.resultados.item', 'planilla.resultados.correctivoAbierto', 'planilla.fotos', 'firmas',
            'informes.generador', 'ordenOrigen', 'resultadoOrigen.item', 'correctivosDerivados',
        ]);
    }

    /**
     * RF-025 y RF-030: una orden se finaliza con las firmas registradas y, si es preventiva,
     * con todos los ítems de la planilla diligenciados.
     */
    private function exigirListaParaFinalizar(OrdenTrabajo $orden): void
    {
        $tipos = $orden->firmas()->pluck('tipo')->all();
        $faltan = array_values(array_filter([
            in_array('tecnico', $tipos, true) ? null : 'la firma del técnico',
            in_array('cliente', $tipos, true) ? null : 'la firma de quien recibe',
        ]));

        if ($orden->llevaPlanilla()) {
            $pendientes = $this->planillas->faltantes($orden->planilla);
            if ($pendientes > 0) {
                array_unshift($faltan, $pendientes === 1 ? '1 ítem de la planilla' : "{$pendientes} ítems de la planilla");
            }
        }

        if ($faltan) {
            throw new ReglaNegocioException('Para finalizar la orden falta '.implode(' y ', $faltan).'.');
        }
    }

    // ------------------------------------------------------------------ internos

    private function cambiar(OrdenTrabajo $orden, string $estado, ?string $detalle = null): void
    {
        $anterior = $orden->estado;
        $orden->update(['estado' => $estado]);
        $this->trazar($orden, 'estado_cambiado', 'estado', $anterior, $estado, $detalle);
    }

    /** RF-029: registro inmutable de cada cambio. */
    private function trazar(OrdenTrabajo $orden, string $accion, ?string $campo, ?string $anterior, ?string $nuevo, ?string $detalle = null): void
    {
        TrazabilidadOrden::create([
            'orden_id' => $orden->id,
            'usuario_id' => Auth::id(),
            'accion' => $accion,
            'campo' => $campo,
            'valor_anterior' => $anterior,
            'valor_nuevo' => $nuevo,
            'detalle' => $detalle,
        ]);
    }

    /** Historial del ascensor y, si es preventiva, ejecución de la visita programada (fase 2). */
    private function alFinalizar(OrdenTrabajo $orden): void
    {
        $orden->equipo?->historial()->create([
            'usuario_id' => Auth::id(),
            'evento' => $orden->servicio === 'correctivo' ? 'reparacion' : 'mantenimiento',
            'descripcion' => "Orden {$orden->numero} finalizada ({$orden->duracion_minutos} min). {$orden->informe}",
        ]);

        $mantenimiento = $orden->mantenimiento;
        if ($mantenimiento && $mantenimiento->estaPendiente()) {
            $this->mantenimientos->ejecutar(
                $mantenimiento,
                $orden->fin_en->toDateString(),
                "Ejecutado con la orden {$orden->numero}."
            );
            $this->trazar($orden, 'mantenimiento_ejecutado', null, null, null, 'La visita programada quedó ejecutada y se recalculó la siguiente.');
        }

        RegistroAuditoriaService::registrar('finalizado', $orden, [
            'duracion_minutos' => $orden->duracion_minutos,
            'informe' => $orden->informe,
        ]);

        // Fase 4: cierra la planilla (avisa los ítems en M pendientes) y emite el informe PDF (RF-039).
        $this->planillas->completar($orden->fresh('planilla'));
        $informe = $this->informes->generar($orden->fresh());
        $this->trazar($orden, 'informe_generado', null, null, "v{$informe->version}", 'Informe técnico en PDF emitido.');
    }

    private function validarTecnicoParaOrden(Tecnico $tecnico, OrdenTrabajo $orden): void
    {
        $nombre = $tecnico->usuario?->nombre ?? 'El técnico';
        if (! $tecnico->usuario?->activo) {
            throw new ReglaNegocioException("{$nombre} está inactivo y no puede recibir órdenes.");
        }
        if ($tecnico->disponibilidad !== 'disponible') {
            throw new ReglaNegocioException("{$nombre} no está disponible".($tecnico->motivo_no_disponible ? " ({$tecnico->motivo_no_disponible})" : '').'.');
        }
        $cruces = $this->tecnicos->conflictos($tecnico, $orden->inicioProgramado(), $orden->finProgramado(), $orden->id);
        if ($cruces->isNotEmpty()) {
            throw new ReglaNegocioException("{$nombre} tiene la orden {$cruces->first()->numero} en un horario que se cruza.");
        }
    }

    private function validarUbicacion(Equipo $equipo, array $datos): void
    {
        if (! empty($datos['edificio_id']) && (int) $datos['edificio_id'] !== $equipo->edificio_id) {
            throw new ReglaNegocioException('El ascensor no pertenece al edificio indicado.');
        }
        $clienteId = $datos['cliente_id'] ?? Edificio::whereKey($equipo->edificio_id)->value('cliente_id');
        if ((int) $clienteId !== $equipo->edificio?->cliente_id) {
            throw new ReglaNegocioException('El edificio no pertenece al cliente indicado.');
        }
    }

    private function validarMantenimiento(MantenimientoProgramado $mantenimiento): void
    {
        if (! $mantenimiento->estaPendiente()) {
            throw new ReglaNegocioException('La visita programada ya fue ejecutada o cancelada.');
        }
        $existente = OrdenTrabajo::where('mantenimiento_id', $mantenimiento->id)->where('estado', '!=', 'cancelada')->value('numero');
        if ($existente) {
            throw new ReglaNegocioException("La visita ya tiene la orden {$existente}.");
        }
    }

    private function exigirNoFinal(OrdenTrabajo $orden): void
    {
        if ($orden->esFinal()) {
            throw new ReglaNegocioException('La orden ya está finalizada o cancelada y no se puede modificar.');
        }
    }

    private function usuariosAsignados(OrdenTrabajo $orden)
    {
        return $orden->tecnicos()->with('usuario')->get()->pluck('usuario')->filter()->values();
    }

    /** RF-025: número único y consecutivo por año (OT-2026-00001). */
    private function siguienteNumero(): string
    {
        $anio = now()->year;
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32("ordenes-{$anio}")]);
        }

        $ultimo = OrdenTrabajo::where('numero', 'like', "OT-{$anio}-%")->orderByDesc('numero')->value('numero');
        $consecutivo = $ultimo ? ((int) substr($ultimo, -5)) + 1 : 1;

        return sprintf('OT-%d-%05d', $anio, $consecutivo);
    }
}
