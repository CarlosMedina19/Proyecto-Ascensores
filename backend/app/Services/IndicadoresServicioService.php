<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Emergencia;
use App\Models\Equipo;
use App\Models\MantenimientoProgramado;
use App\Models\OrdenTrabajo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * Indicadores del servicio en un período (por defecto, los últimos 90 días):
 *
 * - Disponibilidad: 1 − (horas detenidas por fallas y atrapamientos ÷ horas posibles de los ascensores).
 * - MTBF: horas posibles ÷ número de fallas (emergencias por falla o atrapamiento y órdenes correctivas).
 * - Tiempo medio de respuesta: del reporte de la emergencia a la llegada del técnico, y % dentro del objetivo.
 * - Cumplimiento del plan: mantenimientos ejecutados ÷ programados con fecha vencida en el período.
 */
class IndicadoresServicioService
{
    public function calcular(array $filtros = []): array
    {
        $hasta = CarbonImmutable::parse($filtros['hasta'] ?? now())->endOfDay();
        $desde = CarbonImmutable::parse($filtros['desde'] ?? $hasta->subDays(89))->startOfDay();
        $clienteId = $filtros['cliente_id'] ?? null;
        $equipoId = $filtros['equipo_id'] ?? null;

        $equipos = Equipo::query()
            ->where('estado', '!=', 'inactivo')
            ->when($clienteId, fn ($q) => $q->whereHas('edificio', fn ($e) => $e->where('cliente_id', $clienteId)))
            ->when($equipoId, fn ($q) => $q->whereKey($equipoId))
            ->pluck('id');

        $horasPeriodo = $desde->diffInSeconds($hasta) / 3600;
        $horasPosibles = $equipos->count() * $horasPeriodo;

        $emergencias = Emergencia::query()
            ->whereIn('equipo_id', $equipos)
            ->where('estado', '!=', 'cancelada')
            ->where('reportada_en', '<=', $hasta)
            ->where(fn ($q) => $q->whereNull('resuelta_en')->orWhere('resuelta_en', '>=', $desde))
            ->get();

        $fallas = $emergencias->whereIn('tipo', ['falla', 'atrapamiento']);
        $horasDetenidas = $fallas->sum(function (Emergencia $e) use ($desde, $hasta) {
            $inicio = max($e->reportada_en->getTimestamp(), $desde->getTimestamp());
            $fin = min(($e->resuelta_en ?? now())->getTimestamp(), $hasta->getTimestamp());

            return max(0, $fin - $inicio) / 3600;
        });

        $correctivas = OrdenTrabajo::query()
            ->whereIn('equipo_id', $equipos)
            ->where('servicio', 'correctivo')
            ->where('estado', '!=', 'cancelada')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            // La orden creada desde una emergencia es la misma falla: ya se cuenta por la emergencia.
            ->whereNotIn('id', Emergencia::query()->whereNotNull('orden_id')->select('orden_id'))
            ->count();
        $numeroFallas = $fallas->filter(fn (Emergencia $e) => $e->reportada_en->gte($desde))->count() + $correctivas;

        $atendidas = $emergencias->filter(fn (Emergencia $e) => $e->llegada_en && $e->reportada_en->gte($desde));
        $respuestas = $atendidas->map(fn (Emergencia $e) => $e->reportada_en->diffInMinutes($e->llegada_en));

        $mantenimientos = MantenimientoProgramado::query()
            ->whereIn('equipo_id', $equipos)
            ->where('estado', '!=', 'cancelado')
            ->whereBetween('fecha_programada', [$desde->toDateString(), min($hasta, CarbonImmutable::now()->endOfDay())->toDateString()]);
        $programados = (clone $mantenimientos)->count();
        $ejecutados = (clone $mantenimientos)->where('estado', 'ejecutado')->count();

        return [
            'periodo' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'dias' => (int) round($horasPeriodo / 24)],
            'ascensores' => $equipos->count(),
            'disponibilidad' => [
                'porcentaje' => $horasPosibles > 0 ? round(100 * (1 - $horasDetenidas / $horasPosibles), 2) : null,
                'horas_detenidas' => round($horasDetenidas, 1),
            ],
            'mtbf' => [
                'horas' => $numeroFallas > 0 ? round($horasPosibles / $numeroFallas, 0) : null,
                'fallas' => $numeroFallas,
            ],
            'respuesta' => [
                'minutos_promedio' => $respuestas->isNotEmpty() ? (int) round($respuestas->avg()) : null,
                'atendidas' => $atendidas->count(),
                'dentro_objetivo' => $atendidas->filter(fn (Emergencia $e) => $e->reportada_en->diffInMinutes($e->llegada_en) <= $e->minutos_objetivo)->count(),
                'abiertas' => $emergencias->filter(fn (Emergencia $e) => $e->abierta())->count(),
            ],
            'cumplimiento_plan' => [
                'porcentaje' => $programados > 0 ? round(100 * $ejecutados / $programados, 1) : null,
                'ejecutados' => $ejecutados,
                'programados' => $programados,
            ],
        ];
    }

    /** Informe en PDF: los cuatro indicadores y las emergencias del período, con el alcance (cliente o ascensor). */
    public function pdf(array $filtros = []): string
    {
        $datos = $this->calcular($filtros);
        $desde = CarbonImmutable::parse($datos['periodo']['desde'])->startOfDay();
        $hasta = CarbonImmutable::parse($datos['periodo']['hasta'])->endOfDay();
        $equipo = ! empty($filtros['equipo_id']) ? Equipo::with('edificio.cliente')->find($filtros['equipo_id']) : null;
        $cliente = ! empty($filtros['cliente_id']) ? Cliente::find($filtros['cliente_id']) : $equipo?->edificio?->cliente;

        $emergencias = Emergencia::query()
            ->with(['equipo.edificio', 'tecnico.usuario'])
            ->whereBetween('reportada_en', [$desde, $hasta])
            ->when($cliente, fn ($q) => $q->whereHas('equipo.edificio', fn ($e) => $e->where('cliente_id', $cliente->id)))
            ->when($equipo, fn ($q) => $q->where('equipo_id', $equipo->id))
            ->orderBy('reportada_en')
            ->get();

        $html = view('pdf.indicadores', [
            'datos' => $datos,
            'alcance' => $equipo ? "Ascensor {$equipo->codigo} · {$equipo->edificio?->nombre}" : ($cliente?->nombre ?? 'Todos los clientes'),
            'emergencias' => $emergencias,
            'generadoPor' => Auth::user()?->nombre ?? 'Sistema',
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('letter')
            ->setOption('dpi', 96)
            ->setOption('isFontSubsettingEnabled', false)
            ->setOption('fontDir', storage_path('fonts'))
            ->setOption('fontCache', storage_path('fonts'))
            ->output();
    }
}
