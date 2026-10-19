<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\MantenimientoProgramado;
use App\Models\OrdenTrabajo;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Panel de operación: indicadores, mantenimientos por semana, alertas, cola del día,
 * estado por ascensor y técnicos. Todo se calcula con los datos reales de las fases 1 y 2.
 */
class PanelService
{
    private const SEMANAS = 9;

    private const MESES = 6;

    public function __construct(
        private readonly CalendarioHabilService $calendario,
        private readonly PlanillaService $planillas,
    ) {}

    public function obtener(): array
    {
        $hoy = CarbonImmutable::today();
        $semanas = $this->semanas($hoy);
        $actual = end($semanas);

        $tecnicos = $this->tecnicosDelDia($hoy);

        return [
            'franja' => [
                'tecnicos_activos' => $tecnicos->count(),
                // Fase 3: técnicos con una orden en camino o en proceso.
                'tecnicos_en_campo' => $tecnicos->where('en_campo', true)->count(),
                'proximo_festivo' => $this->proximoFestivo($hoy),
                'cumplimiento_semana' => ['ejecutados' => $actual['ejecutados'], 'programados' => $actual['programados']],
            ],
            'indicadores' => [
                'al_dia' => $this->alDia($hoy),
                'ascensores_activos' => $this->ascensoresActivos($hoy),
                'atrasados' => $this->atrasados($hoy, $semanas),
                'correctivos' => $this->correctivos($hoy, $semanas),
                'contratos' => $this->contratos($hoy),
            ],
            'semanas' => $semanas,
            'alertas' => $this->alertas($hoy),
            'cola_hoy' => $this->colaHoy($hoy),
            'estado_ascensores' => $this->estadoAscensores($hoy),
            'tecnicos' => $tecnicos->map(fn (array $t) => collect($t)->except('en_campo')->all())->values()->all(),
            'generado_en' => now()->toIso8601String(),
        ];
    }

    /** RF-025: órdenes correctivas abiertas y las creadas por semana. */
    private function correctivos(CarbonImmutable $hoy, array $semanas): array
    {
        $correctivos = OrdenTrabajo::where('servicio', 'correctivo');
        $inicio = CarbonImmutable::parse($semanas[0]['inicio']);
        $creados = (clone $correctivos)->where('created_at', '>=', $inicio)->pluck('created_at');

        return [
            'abiertos' => (clone $correctivos)->activas()->count(),
            'esta_semana' => (clone $correctivos)->where('created_at', '>=', $hoy->startOfWeek())->count(),
            'criticos' => (clone $correctivos)->activas()->where('prioridad', 'critica')->count(),
            'serie' => array_map(fn ($s) => [
                'semana' => $s['semana'],
                'valor' => $creados->filter(fn ($fecha) => $fecha->between(CarbonImmutable::parse($s['inicio']), CarbonImmutable::parse($s['inicio'])->endOfWeek()))->count(),
            ], $semanas),
        ];
    }

    /**
     * Técnicos activos con su ocupación de hoy (minutos de órdenes sobre una jornada de 8 horas)
     * y lo que están haciendo.
     */
    private function tecnicosDelDia(CarbonImmutable $hoy): Collection
    {
        $jornada = 8 * 60;

        return $this->tecnicos()->load(['tecnico.ordenes' => fn ($c) => $c
            ->with('edificio')
            ->whereDate('fecha', $hoy->toDateString())
            ->where('estado', '!=', 'cancelada')
            ->orderBy('hora'),
        ])->map(function (Usuario $usuario) use ($jornada) {
            $perfil = $usuario->tecnico;
            $ordenes = $perfil?->ordenes ?? collect();
            $enCurso = $ordenes->first(fn (OrdenTrabajo $o) => in_array($o->estado, ['en_camino', 'en_proceso'], true));
            $siguiente = $ordenes->first(fn (OrdenTrabajo $o) => in_array($o->estado, ['programada', 'asignada', 'pendiente'], true));
            $minutos = $ordenes->sum('duracion_estimada');

            $estado = match (true) {
                $perfil === null => 'Perfil de técnico incompleto',
                $enCurso !== null => ($enCurso->estado === 'en_camino' ? 'En camino' : 'En proceso')." · {$enCurso->edificio?->nombre}",
                $perfil->disponibilidad !== 'disponible' => 'No disponible'.($perfil->motivo_no_disponible ? " · {$perfil->motivo_no_disponible}" : ''),
                $siguiente !== null => 'Próxima orden '.substr((string) $siguiente->hora, 0, 5)." · {$siguiente->edificio?->nombre}",
                default => 'Disponible',
            };

            return [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'ultimo_acceso_en' => $usuario->ultimo_acceso_en?->toIso8601String(),
                'estado' => $estado,
                'ordenes_hoy' => $ordenes->count(),
                'ocupacion' => $perfil ? (int) min(100, round($minutos * 100 / $jornada)) : null,
                'en_campo' => $enCurso !== null,
            ];
        });
    }

    /** Visitas no canceladas: base de todos los conteos. */
    private function visitas()
    {
        return MantenimientoProgramado::query()->where('estado', '!=', 'cancelado');
    }

    /** Programados frente a ejecutados por semana (lunes a domingo), la última es la actual. */
    private function semanas(CarbonImmutable $hoy): array
    {
        $inicio = $hoy->startOfWeek()->subWeeks(self::SEMANAS - 1);
        $fin = $hoy->endOfWeek();

        $filas = $this->visitas()
            ->whereBetween('fecha_programada', [$inicio->toDateString(), $fin->toDateString()])
            ->get(['fecha_programada', 'estado']);

        $resultado = [];
        for ($i = 0; $i < self::SEMANAS; $i++) {
            $desde = $inicio->addWeeks($i);
            $hasta = $desde->endOfWeek();
            $deLaSemana = $filas->filter(fn ($m) => $m->fecha_programada->between($desde, $hasta));
            $resultado[] = [
                'semana' => (int) $desde->isoWeek(),
                'inicio' => $desde->toDateString(),
                'programados' => $deLaSemana->count(),
                'ejecutados' => $deLaSemana->where('estado', 'ejecutado')->count(),
                'actual' => $i === self::SEMANAS - 1,
            ];
        }

        return $resultado;
    }

    /**
     * Porcentaje de visitas vencidas (fecha ya pasada) que se ejecutaron: últimos 90 días,
     * y la serie mensual de los últimos 6 meses.
     */
    private function alDia(CarbonImmutable $hoy): array
    {
        $porcentaje = fn (CarbonImmutable $desde, CarbonImmutable $hasta) => $this->porcentajeEjecutado($desde, $hasta);

        $serie = [];
        for ($i = self::MESES - 1; $i >= 0; $i--) {
            $mes = $hoy->startOfMonth()->subMonths($i);
            $hasta = $i === 0 ? $hoy->subDay() : $mes->endOfMonth();
            $serie[] = ['mes' => $mes->format('Y-m'), 'valor' => $porcentaje($mes, $hasta)];
        }

        $actual = $serie[self::MESES - 1]['valor'];
        $anterior = $serie[self::MESES - 2]['valor'];

        return [
            'porcentaje' => $porcentaje($hoy->subDays(90), $hoy->subDay()),
            'variacion' => $actual !== null && $anterior !== null ? round($actual - $anterior, 1) : null,
            'serie' => $serie,
        ];
    }

    private function porcentajeEjecutado(CarbonImmutable $desde, CarbonImmutable $hasta): ?float
    {
        if ($hasta->lt($desde)) {
            return null;
        }

        $base = $this->visitas()->whereBetween('fecha_programada', [$desde->toDateString(), $hasta->toDateString()]);
        $total = (clone $base)->count();

        return $total === 0 ? null : round((clone $base)->where('estado', 'ejecutado')->count() * 100 / $total, 1);
    }

    private function ascensoresActivos(CarbonImmutable $hoy): array
    {
        $serie = [];
        for ($i = self::MESES - 1; $i >= 0; $i--) {
            $fin = $hoy->startOfMonth()->subMonths($i)->endOfMonth();
            $serie[] = ['mes' => $fin->format('Y-m'), 'valor' => Equipo::where('created_at', '<=', $fin)->count()];
        }

        return [
            'total' => Equipo::where('estado', 'activo')->count(),
            'registrados' => Equipo::count(),
            'edificios' => Edificio::whereHas('equipos', fn ($c) => $c->where('estado', 'activo'))->count(),
            'serie' => $serie,
        ];
    }

    private function atrasados(CarbonImmutable $hoy, array $semanas): array
    {
        $pendientes = MantenimientoProgramado::pendientes();

        return [
            'total' => (clone $pendientes)->whereDate('fecha_programada', '<', $hoy->toDateString())->count(),
            'esta_semana' => (clone $pendientes)
                ->whereBetween('fecha_programada', [$hoy->toDateString(), $hoy->endOfWeek()->toDateString()])
                ->count(),
            // Visitas que quedaron sin ejecutar en cada semana.
            'serie' => array_map(fn ($s) => ['semana' => $s['semana'], 'valor' => $s['programados'] - $s['ejecutados']], $semanas),
        ];
    }

    private function contratos(CarbonImmutable $hoy): array
    {
        $vigentes = Contrato::where('estado', 'vigente');

        return [
            'vigentes' => (clone $vigentes)->count(),
            'valor' => (string) ((clone $vigentes)->sum('valor')),
            'por_vencer' => (clone $vigentes)->whereDate('fecha_fin', '<=', $hoy->addDays(30)->toDateString())->count(),
        ];
    }

    private function proximoFestivo(CarbonImmutable $hoy): ?array
    {
        foreach ([$hoy->year, $hoy->year + 1] as $anio) {
            foreach ($this->calendario->festivos($anio) as $fecha => $nombre) {
                if ($fecha >= $hoy->toDateString()) {
                    return ['fecha' => $fecha, 'nombre' => $nombre];
                }
            }
        }

        return null;
    }

    private function alertas(CarbonImmutable $hoy): array
    {
        $alertas = [];

        // RF-027: las órdenes críticas abiertas van primero.
        $criticas = OrdenTrabajo::with(['equipo', 'edificio', 'tecnicos.usuario'])
            ->activas()
            ->where('prioridad', 'critica')
            ->orderBy('fecha')
            ->orderBy('hora')
            ->limit(2)
            ->get();
        foreach ($criticas as $orden) {
            $tecnicos = $orden->tecnicos->map(fn ($t) => $t->usuario?->nombre)->filter()->implode(', ');
            $alertas[] = [
                'tipo' => 'peligro',
                'titulo' => "Orden crítica {$orden->numero} · {$orden->equipo?->codigo}",
                'detalle' => collect([$orden->edificio?->nombre, $orden->descripcion, $tecnicos ? "Técnicos: {$tecnicos}" : 'Sin técnico asignado'])
                    ->filter()->implode(' · '),
                'enlace' => "/ordenes/{$orden->id}",
            ];
        }

        $atrasadas = MantenimientoProgramado::pendientes()
            ->with('equipo.edificio.cliente')
            ->whereDate('fecha_programada', '<', $hoy->toDateString())
            ->orderBy('fecha_programada')
            ->limit(2)
            ->get();
        foreach ($atrasadas as $visita) {
            $alertas[] = [
                'tipo' => 'peligro',
                'titulo' => "Mantenimiento atrasado · {$visita->equipo?->codigo}",
                'detalle' => trim(($visita->equipo?->edificio?->nombre ?? '').' · '.($visita->equipo?->edificio?->cliente?->nombre ?? '')
                    .' · programado el '.$visita->fecha_programada->translatedFormat('j \d\e F'), ' ·'),
                'enlace' => "/equipos/{$visita->equipo_id}",
            ];
        }

        $porVencer = Contrato::with('cliente')
            ->where('estado', 'vigente')
            ->whereDate('fecha_fin', '<=', $hoy->addDays(30)->toDateString())
            ->orderBy('fecha_fin')
            ->limit(2)
            ->get();
        foreach ($porVencer as $contrato) {
            $dias = $contrato->diasParaVencer();
            $alertas[] = [
                'tipo' => 'alerta',
                'titulo' => $dias <= 0 ? "Contrato {$contrato->numero} vence hoy" : "Contrato por vencer en {$dias} días",
                'detalle' => "{$contrato->cliente?->nombre} · vence el ".$contrato->fecha_fin->translatedFormat('j \d\e F'),
                'enlace' => "/contratos/{$contrato->id}",
            ];
        }

        return $alertas;
    }

    /** Órdenes de hoy y visitas del plan que aún no tienen orden, por hora. */
    private function colaHoy(CarbonImmutable $hoy): array
    {
        $ordenes = OrdenTrabajo::with(['equipo', 'edificio', 'tecnicos.usuario'])
            ->whereDate('fecha', $hoy->toDateString())
            ->where('estado', '!=', 'cancelada')
            ->get()
            ->map(fn (OrdenTrabajo $orden) => [
                'tipo' => 'orden',
                'id' => $orden->id,
                'numero' => $orden->numero,
                'hora' => substr((string) $orden->hora, 0, 5),
                'equipo_id' => $orden->equipo_id,
                'equipo' => $orden->equipo?->codigo,
                'edificio' => $orden->edificio?->nombre,
                'servicio' => $orden->servicio,
                'descripcion' => $orden->descripcion,
                'prioridad' => $orden->prioridad,
                'estado' => $orden->estado,
                'tecnicos' => $orden->tecnicos->map(fn ($t) => $t->usuario?->nombre)->filter()->values()->all(),
            ]);

        $visitas = $this->visitas()
            ->with(['equipo.edificio', 'asignacion.plan'])
            ->whereDate('fecha_programada', $hoy->toDateString())
            ->whereDoesntHave('ordenActiva')
            ->whereDoesntHave('ordenes', fn ($c) => $c->where('estado', 'finalizada'))
            ->get()
            ->map(fn (MantenimientoProgramado $visita) => [
                'tipo' => 'visita',
                'id' => $visita->id,
                'hora' => $visita->hora ? substr($visita->hora, 0, 5) : null,
                'equipo_id' => $visita->equipo_id,
                'equipo' => $visita->equipo?->codigo,
                'edificio' => $visita->equipo?->edificio?->nombre,
                'servicio' => 'preventivo',
                'descripcion' => $visita->asignacion?->plan?->nombre,
                'prioridad' => null,
                'estado' => $visita->estado,
                'tecnicos' => [],
            ]);

        return $ordenes->concat($visitas)->sortBy('hora')->values()->all();
    }

    /** Hasta 8 ascensores, primero los que requieren atención. */
    private function estadoAscensores(CarbonImmutable $hoy): array
    {
        $atrasados = MantenimientoProgramado::pendientes()
            ->whereDate('fecha_programada', '<', $hoy->toDateString())
            ->distinct()
            ->pluck('equipo_id')
            ->flip();

        $prioridad = ['mantenimiento' => 1, 'inactivo' => 2, 'activo' => 3];

        return Equipo::with(['edificio.cliente', 'ascensor', 'asignacionActiva.plan'])
            ->get()
            ->sortBy(fn (Equipo $equipo) => [isset($atrasados[$equipo->id]) ? 0 : 1, $prioridad[$equipo->estado] ?? 9, $equipo->codigo])
            ->take(8)
            ->map(fn (Equipo $equipo) => [
                'id' => $equipo->id,
                'codigo' => $equipo->codigo,
                'ubicacion' => $equipo->ubicacion,
                'edificio' => $equipo->edificio?->nombre,
                'cliente' => $equipo->edificio?->cliente?->nombre,
                'estado' => $equipo->estado,
                'paradas' => $equipo->ascensor?->paradas,
                'plan' => $equipo->asignacionActiva?->plan?->nombre,
                'proximo_mantenimiento' => $equipo->asignacionActiva?->proximo_mantenimiento?->toDateString(),
                'atrasado' => isset($atrasados[$equipo->id]),
                // Fase 4: estado por sección de la última planilla (null si aún no tiene).
                'ultima_revision' => $this->planillas->ultimaRevision($equipo->id),
            ])
            ->values()
            ->all();
    }

    /** @return Collection<int, Usuario> */
    private function tecnicos(): Collection
    {
        return Usuario::where('activo', true)
            ->whereHas('rol', fn ($rol) => $rol->where('nombre', 'tecnico'))
            ->orderBy('nombre')
            ->get();
    }
}
