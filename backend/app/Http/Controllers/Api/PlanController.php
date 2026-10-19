<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Plan\ActualizarPlanRequest;
use App\Http\Requests\Plan\ActualizarPreciosPlanRequest;
use App\Http\Requests\Plan\GuardarPlanRequest;
use App\Http\Resources\HistorialPrecioResource;
use App\Http\Resources\PlanMantenimientoResource;
use App\Models\PlanMantenimiento;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * RF-010, RF-012 y RF-015: catálogo de planes, precios por periodicidad e historial de precios.
 */
class PlanController extends Controller
{
    public function __construct(
        protected PlanService $servicioPlan
    ) {}

    public function index(Request $solicitud): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PlanMantenimiento::class);

        return PlanMantenimientoResource::collection(
            $this->servicioPlan->listar($solicitud->all(), $this->porPagina($solicitud))
        );
    }

    public function store(GuardarPlanRequest $solicitud): JsonResponse
    {
        $this->authorize('create', PlanMantenimiento::class);
        $plan = $this->servicioPlan->crear($solicitud->validated());

        return (new PlanMantenimientoResource($plan->load('precios.periodicidad')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PlanMantenimiento $plan): PlanMantenimientoResource
    {
        $this->authorize('view', $plan);

        return new PlanMantenimientoResource($plan->load('precios.periodicidad')->loadCount([
            'asignaciones as asignaciones_activas_count' => fn ($c) => $c->where('estado', 'activa'),
        ]));
    }

    public function update(ActualizarPlanRequest $solicitud, PlanMantenimiento $plan): PlanMantenimientoResource
    {
        $this->authorize('update', $plan);
        $actualizado = $this->servicioPlan->actualizar($plan, $solicitud->validated());

        return new PlanMantenimientoResource($actualizado->load('precios.periodicidad'));
    }

    public function actualizarPrecios(ActualizarPreciosPlanRequest $solicitud, PlanMantenimiento $plan): JsonResponse
    {
        $this->authorize('cambiarPrecios', $plan);
        $cambios = $this->servicioPlan->actualizarPrecios($plan, $solicitud->validated('precios'), $solicitud->validated('motivo'));

        return (new PlanMantenimientoResource($plan->fresh('precios.periodicidad')))
            ->additional(['mensaje' => $cambios > 0 ? 'Precios actualizados correctamente' : 'Los precios no cambiaron'])
            ->response();
    }

    public function historialPrecios(Request $solicitud, PlanMantenimiento $plan): AnonymousResourceCollection
    {
        $this->authorize('view', $plan);

        return HistorialPrecioResource::collection(
            // Solo los cambios del catálogo; los de cada ascensor se ven en su asignación.
            $plan->historialPrecios()
                ->whereNull('asignacion_id')
                ->with(['periodicidad', 'usuario'])
                ->latest('created_at')
                ->latest('id')
                ->paginate($this->porPagina($solicitud), ['*'], 'pagina')
        );
    }

    public function destroy(PlanMantenimiento $plan): JsonResponse
    {
        $this->authorize('delete', $plan);
        $this->servicioPlan->eliminar($plan);

        return response()->json([
            'exito' => true,
            'mensaje' => 'Plan eliminado correctamente',
        ]);
    }
}
