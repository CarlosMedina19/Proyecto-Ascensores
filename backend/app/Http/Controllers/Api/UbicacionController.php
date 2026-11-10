<?php

namespace App\Http\Controllers\Api;

use App\Events\UbicacionTecnicoActualizada;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Models\OrdenTrabajo;
use App\Models\UbicacionTecnico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ubicación en vivo de los técnicos: la envían (app o navegador) mientras su orden está en proceso
 * y Auditoría la muestra en el mapa.
 */
class UbicacionController extends Controller
{
    public function registrar(Request $solicitud, OrdenTrabajo $orden): JsonResponse
    {
        $usuario = $solicitud->user();
        abort_unless($usuario->esTecnico() && $usuario->tecnico && $orden->tieneAsignado($usuario), 403, 'Solo un técnico asignado a la orden envía su ubicación.');
        if ($orden->estado !== 'en_proceso') {
            throw new ReglaNegocioException('La ubicación solo se registra mientras la orden está en proceso.');
        }
        $datos = $solicitud->validate([
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'precision_metros' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'origen' => ['nullable', 'in:app,web'],
        ]);

        $ubicacion = UbicacionTecnico::create([
            'tecnico_id' => $usuario->tecnico->id,
            'orden_id' => $orden->id,
            'latitud' => $datos['latitud'],
            'longitud' => $datos['longitud'],
            'precision_metros' => $datos['precision_metros'] ?? null,
            'origen' => $datos['origen'] ?? 'app',
            'registrada_en' => now(),
        ]);
        try {
            UbicacionTecnicoActualizada::dispatch($ubicacion);
        } catch (Throwable $e) {
            Log::warning('Tiempo real: no se pudo anunciar la ubicación', ['error' => $e->getMessage()]);
        }

        return response()->json(['datos' => $ubicacion->resumen()], 201);
    }

    /** Técnicos con una orden en proceso y su última posición conocida. */
    public function enVivo(Request $solicitud): JsonResponse
    {
        abort_unless($solicitud->user()->tienePermiso('registros_auditoria.ver'), 403, 'Su rol no puede ver la auditoría.');

        $ordenes = OrdenTrabajo::with(['equipo', 'edificio', 'tecnicos.usuario'])->where('estado', 'en_proceso')->orderBy('inicio_en')->get();
        $ids = $ordenes->pluck('id');
        $ultimas = UbicacionTecnico::whereIn('id', UbicacionTecnico::selectRaw('max(id)')->whereIn('orden_id', $ids)->groupBy('orden_id', 'tecnico_id'))
            ->get()
            ->keyBy(fn (UbicacionTecnico $u) => $u->orden_id.'-'.$u->tecnico_id);

        $activos = $ordenes->flatMap(fn (OrdenTrabajo $orden) => $orden->tecnicos->map(function ($tecnico) use ($orden, $ultimas) {
            $ubicacion = $ultimas->get($orden->id.'-'.$tecnico->id);

            return [
                'tecnico_id' => $tecnico->id,
                'tecnico' => $tecnico->usuario?->nombre,
                'orden' => ['id' => $orden->id, 'numero' => $orden->numero, 'estado' => $orden->estado],
                'equipo' => $orden->equipo?->codigo,
                'edificio' => $orden->edificio?->nombre,
                'inicio_en' => $orden->inicio_en?->toIso8601String(),
                'latitud' => $ubicacion?->latitud,
                'longitud' => $ubicacion?->longitud,
                'precision_metros' => $ubicacion?->precision_metros,
                'registrada_en' => $ubicacion?->registrada_en?->toIso8601String(),
            ];
        }))->values();

        return response()->json(['datos' => $activos]);
    }
}
