<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asignacion\CambiarPlanRequest;
use App\Http\Requests\Asignacion\CambiarPrecioRequest;
use App\Http\Requests\Asignacion\CambiarVigenciaRequest;
use App\Http\Requests\Asignacion\GuardarAsignacionRequest;
use App\Http\Requests\MotivoRequest;
use App\Http\Resources\AsignacionPlanResource;
use App\Models\AsignacionPlan;
use App\Services\AsignacionPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * RF-013, RF-014, RF-015 y RF-016: plan asignado a cada ascensor.
 */
class AsignacionPlanController extends Controller
{
    public function __construct(
        protected AsignacionPlanService $servicioAsignacion
    ) {}

    public function index(Request $solicitud): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AsignacionPlan::class);

        return AsignacionPlanResource::collection(
            $this->servicioAsignacion->listar($solicitud->all(), $this->porPagina($solicitud))
        );
    }

    public function store(GuardarAsignacionRequest $solicitud): JsonResponse
    {
        $this->authorize('create', AsignacionPlan::class);
        $asignacion = $this->servicioAsignacion->asignar($solicitud->validated());

        return (new AsignacionPlanResource($this->detalle($asignacion)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AsignacionPlan $asignacion): AsignacionPlanResource
    {
        $this->authorize('view', $asignacion);

        return new AsignacionPlanResource($this->detalle($asignacion)->load([
            'cambios' => fn ($c) => $c->with('usuario')->latest('created_at')->latest('id'),
            'historialPrecios' => fn ($c) => $c->with('usuario')->latest('created_at')->latest('id'),
        ]));
    }

    public function cambiarPlan(CambiarPlanRequest $solicitud, AsignacionPlan $asignacion): AsignacionPlanResource
    {
        $this->authorize('update', $asignacion);

        return new AsignacionPlanResource($this->detalle(
            $this->servicioAsignacion->cambiarPlan($asignacion, $solicitud->validated())
        ));
    }

    public function cambiarPrecio(CambiarPrecioRequest $solicitud, AsignacionPlan $asignacion): AsignacionPlanResource
    {
        $this->authorize('cambiarPrecio', $asignacion);

        return new AsignacionPlanResource($this->detalle(
            $this->servicioAsignacion->cambiarPrecio($asignacion, (string) $solicitud->validated('precio'), $solicitud->validated('motivo'))
        ));
    }

    public function cambiarVigencia(CambiarVigenciaRequest $solicitud, AsignacionPlan $asignacion): AsignacionPlanResource
    {
        $this->authorize('update', $asignacion);

        return new AsignacionPlanResource($this->detalle(
            $this->servicioAsignacion->cambiarVigencia($asignacion, $solicitud->validated('fecha_fin'), $solicitud->validated('motivo'))
        ));
    }

    public function finalizar(MotivoRequest $solicitud, AsignacionPlan $asignacion): AsignacionPlanResource
    {
        $this->authorize('update', $asignacion);

        return new AsignacionPlanResource($this->detalle(
            $this->servicioAsignacion->finalizar($asignacion, $solicitud->validated('motivo'))
        ));
    }

    private function detalle(AsignacionPlan $asignacion): AsignacionPlan
    {
        return $asignacion->load([
            'equipo.edificio.cliente',
            'plan',
            'periodicidad',
            'contrato',
            'mantenimientos' => fn ($c) => $c->with('ordenActiva')->where('estado', '!=', 'cancelado')->orderBy('fecha_programada'),
        ]);
    }
}
