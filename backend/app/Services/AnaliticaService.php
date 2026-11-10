<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Emergencia;
use App\Models\Equipo;
use App\Models\MantenimientoProgramado;
use App\Models\OrdenTrabajo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integración con el microservicio de analítica en Python (backend/analitica).
 *
 * Laravel sigue siendo el núcleo: el controlador ya validó identidad y permisos; aquí se reúnen solo
 * los registros que el usuario puede ver (su cliente o su ascensor), se envían por la red interna de
 * Docker con la clave de servicio y se devuelve el JSON calculado por Python.
 */
class AnaliticaService
{
    /** Analítica descriptiva: series mensuales y totales. */
    public function resumen(array $filtros): array
    {
        return $this->consultar('/api/v1/indicadores/resumen', $filtros);
    }

    /** Analítica predictiva: pronóstico de emergencias y riesgo de falla por ascensor. */
    public function predicciones(array $filtros): array
    {
        return $this->consultar('/api/v1/predicciones', $filtros);
    }

    private function consultar(string $ruta, array $filtros): array
    {
        $url = rtrim((string) config('services.analitica.url'), '/');
        if ($url === '' || blank(config('services.analitica.clave'))) {
            throw new ReglaNegocioException('El servicio de analítica no está configurado.');
        }
        $datos = $this->datos($filtros);
        // El mismo cálculo con los mismos datos se reutiliza unos minutos.
        $clave = 'analitica:'.md5($url.$ruta.json_encode($datos));

        return Cache::remember($clave, now()->addMinutes(10), function () use ($url, $ruta, $datos) {
            try {
                $respuesta = Http::withHeaders(['X-Api-Key' => config('services.analitica.clave')])
                    ->acceptJson()
                    ->timeout((int) config('services.analitica.tiempo', 20))
                    ->post($url.$ruta, $datos);
            } catch (ConnectionException $e) {
                Log::warning('Analítica: sin conexión con el servicio', ['error' => $e->getMessage()]);
                throw new ReglaNegocioException('El servicio de analítica no responde. Intente de nuevo en unos minutos.');
            }
            if (! $respuesta->successful()) {
                Log::warning('Analítica: respuesta con error', ['estado' => $respuesta->status()]);
                throw new ReglaNegocioException('El servicio de analítica no pudo calcular los indicadores.');
            }

            return $respuesta->json();
        });
    }

    /**
     * Contrato de datos con Python: solo los campos necesarios, sin datos personales.
     *
     * @return array<string, mixed>
     */
    public function datos(array $filtros): array
    {
        $hasta = CarbonImmutable::parse($filtros['hasta'] ?? now())->endOfDay();
        $desde = CarbonImmutable::parse($filtros['desde'] ?? $hasta->subMonthsNoOverflow(11)->startOfMonth())->startOfDay();
        $clienteId = $filtros['cliente_id'] ?? null;
        $equipoId = $filtros['equipo_id'] ?? null;

        $equipos = Equipo::with('edificio')
            ->where('estado', '!=', 'inactivo')
            ->when($clienteId, fn ($q) => $q->whereHas('edificio', fn ($e) => $e->where('cliente_id', $clienteId)))
            ->when($equipoId, fn ($q) => $q->whereKey($equipoId))
            ->get();
        $ids = $equipos->pluck('id');

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'equipos' => $equipos->map(fn (Equipo $e) => [
                'id' => $e->id,
                'codigo' => $e->codigo,
                'edificio' => $e->edificio?->nombre,
                'anio_instalacion' => $e->fecha_instalacion?->year,
            ])->values()->all(),
            'ordenes' => OrdenTrabajo::whereIn('equipo_id', $ids)->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->get(['equipo_id', 'servicio', 'estado', 'fecha', 'duracion_minutos'])
                ->map(fn ($o) => [
                    'equipo_id' => $o->equipo_id,
                    'servicio' => $o->servicio,
                    'estado' => $o->estado,
                    'fecha' => $o->fecha->toDateString(),
                    'duracion_minutos' => $o->duracion_minutos,
                ])->values()->all(),
            // Las emergencias de los 6 meses anteriores también cuentan para el riesgo de falla.
            'emergencias' => Emergencia::whereIn('equipo_id', $ids)->where('estado', '!=', 'cancelada')
                ->whereBetween('reportada_en', [$desde->subMonths(6), $hasta])
                ->get(['equipo_id', 'tipo', 'reportada_en', 'llegada_en', 'resuelta_en', 'minutos_objetivo'])
                ->map(fn ($e) => [
                    'equipo_id' => $e->equipo_id,
                    'tipo' => $e->tipo,
                    'reportada_en' => $e->reportada_en?->toIso8601String(),
                    'llegada_en' => $e->llegada_en?->toIso8601String(),
                    'resuelta_en' => $e->resuelta_en?->toIso8601String(),
                    'minutos_objetivo' => $e->minutos_objetivo,
                ])->values()->all(),
            'visitas' => MantenimientoProgramado::whereIn('equipo_id', $ids)
                ->whereBetween('fecha_programada', [$desde->subMonths(6)->toDateString(), $hasta->toDateString()])
                ->get(['equipo_id', 'fecha_programada', 'estado', 'fecha_ejecucion'])
                ->map(fn ($v) => [
                    'equipo_id' => $v->equipo_id,
                    'fecha_programada' => $v->fecha_programada->toDateString(),
                    'estado' => $v->estado,
                    'fecha_ejecucion' => $v->fecha_ejecucion?->toDateString(),
                ])->values()->all(),
        ];
    }
}
