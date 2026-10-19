<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MotivoRequest;
use App\Http\Requests\Orden\AccionOrdenRequest;
use App\Http\Requests\Orden\GuardarOrdenRequest;
use App\Http\Resources\OrdenTrabajoResource;
use App\Models\OrdenTrabajo;
use App\Services\OrdenTrabajoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * RF-023 a RF-029: agenda y órdenes de trabajo.
 */
class OrdenTrabajoController extends Controller
{
    public function __construct(
        protected OrdenTrabajoService $servicioOrdenes
    ) {}

    public function index(Request $solicitud): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OrdenTrabajo::class);

        return OrdenTrabajoResource::collection(
            $this->servicioOrdenes->listar($solicitud->all(), $this->porPagina($solicitud))
        );
    }

    /** RF-023: órdenes de un rango (día, semana o mes) para la agenda. */
    public function agenda(Request $solicitud): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OrdenTrabajo::class);
        $datos = $solicitud->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'tecnico_id' => ['nullable', 'integer'],
            'servicio' => ['nullable', 'string'],
            'estado' => ['nullable', 'string'],
        ]);
        if (CarbonImmutable::parse($datos['desde'])->diffInDays(CarbonImmutable::parse($datos['hasta'])) > 100) {
            abort(response()->json(['mensaje' => 'El rango de fechas no puede superar 100 días.', 'errores' => ['hasta' => ['El rango de fechas no puede superar 100 días.']]], 422));
        }

        return OrdenTrabajoResource::collection(
            $this->servicioOrdenes->agenda($datos['desde'], $datos['hasta'], $datos)
        );
    }

    public function store(GuardarOrdenRequest $solicitud): JsonResponse
    {
        $this->authorize('create', OrdenTrabajo::class);
        if ($solicitud->filled('tecnicos')) {
            $this->authorize('asignar', new OrdenTrabajo);
        }
        $orden = $this->servicioOrdenes->crear($solicitud->validated());

        return (new OrdenTrabajoResource($orden))->response()->setStatusCode(201);
    }

    public function show(OrdenTrabajo $orden): OrdenTrabajoResource
    {
        $this->authorize('view', $orden);

        return new OrdenTrabajoResource($this->servicioOrdenes->detalle($orden));
    }

    public function asignar(AccionOrdenRequest $solicitud, OrdenTrabajo $orden): OrdenTrabajoResource
    {
        $this->authorize('asignar', $orden);

        return new OrdenTrabajoResource($this->servicioOrdenes->asignar($orden, $solicitud->validated('tecnicos')));
    }

    public function reprogramar(AccionOrdenRequest $solicitud, OrdenTrabajo $orden): OrdenTrabajoResource
    {
        $this->authorize('update', $orden);
        $datos = $solicitud->validated();

        return new OrdenTrabajoResource($this->servicioOrdenes->reprogramar($orden, $datos, $datos['motivo']));
    }

    public function prioridad(AccionOrdenRequest $solicitud, OrdenTrabajo $orden): OrdenTrabajoResource
    {
        $this->authorize('update', $orden);

        return new OrdenTrabajoResource(
            $this->servicioOrdenes->cambiarPrioridad($orden, $solicitud->validated('prioridad'), $solicitud->validated('motivo'))
        );
    }

    /** RF-026: en camino, en proceso, pendiente o finalizada (solo un técnico asignado). */
    public function estado(AccionOrdenRequest $solicitud, OrdenTrabajo $orden): OrdenTrabajoResource
    {
        $this->authorize('ejecutar', $orden);
        $datos = $solicitud->validated();

        return new OrdenTrabajoResource($this->servicioOrdenes->avanzar($orden, $datos['estado'], $datos));
    }

    public function cancelar(MotivoRequest $solicitud, OrdenTrabajo $orden): OrdenTrabajoResource
    {
        $this->authorize('cancelar', $orden);

        return new OrdenTrabajoResource($this->servicioOrdenes->cancelar($orden, $solicitud->validated('motivo')));
    }
}
