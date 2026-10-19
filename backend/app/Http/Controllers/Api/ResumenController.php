<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HistorialEquipoResource;
use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\HistorialEquipo;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;

/**
 * Panel inicial de la fase 1.
 */
class ResumenController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('verResumen');

        $equiposPorEstado = Equipo::query()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $ultimosEventos = HistorialEquipo::with(['usuario', 'equipo'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return response()->json([
            'exito' => true,
            'mensaje' => 'Resumen de SIGMA',
            'datos' => [
                'usuarios' => [
                    'total' => Usuario::count(),
                    'activos' => Usuario::where('activo', true)->count(),
                ],
                'clientes' => [
                    'total' => Cliente::count(),
                    'activos' => Cliente::where('estado', true)->count(),
                ],
                'edificios' => [
                    'total' => Edificio::count(),
                    'activos' => Edificio::where('activo', true)->count(),
                ],
                'equipos' => [
                    'total' => (int) $equiposPorEstado->sum(),
                    'activo' => (int) ($equiposPorEstado['activo'] ?? 0),
                    'inactivo' => (int) ($equiposPorEstado['inactivo'] ?? 0),
                    'mantenimiento' => (int) ($equiposPorEstado['mantenimiento'] ?? 0),
                ],
                'ultimos_eventos' => $ultimosEventos->map(fn (HistorialEquipo $evento) => [
                    ...(new HistorialEquipoResource($evento))->resolve(),
                    'equipo_codigo' => $evento->equipo?->codigo,
                ]),
            ],
            'generado_en' => now()->toIso8601String(),
        ]);
    }
}
