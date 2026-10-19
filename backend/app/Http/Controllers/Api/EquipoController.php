<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Equipo\ActualizarEquipoRequest;
use App\Http\Requests\Equipo\GuardarEquipoRequest;
use App\Http\Requests\Equipo\RegistrarHistorialEquipoRequest;
use App\Http\Resources\EquipoResource;
use App\Http\Resources\HistorialEquipoResource;
use App\Models\Equipo;
use App\Services\EquipoService;
use App\Services\PlanillaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EquipoController extends Controller
{
    public function __construct(
        protected EquipoService $servicioEquipo
    ) {}

    public function index(Request $solicitud): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Equipo::class);
        $equipos = $this->servicioEquipo->listar($solicitud->all(), $this->porPagina($solicitud));

        return EquipoResource::collection($equipos);
    }

    public function store(GuardarEquipoRequest $solicitud): JsonResponse
    {
        $this->authorize('create', Equipo::class);
        $equipo = $this->servicioEquipo->crear($solicitud->validated());

        return (new EquipoResource($equipo))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Equipo $equipo): EquipoResource
    {
        $this->authorize('view', $equipo);

        $equipo->load(['edificio.cliente', 'ascensor', 'asignacionActiva.plan', 'asignacionActiva.periodicidad', 'asignacionActiva.contrato', 'fotos']);
        // Fase 4: estado de cada sección según la última planilla completa (esquema del ascensor).
        $equipo->setAttribute('ultima_revision', app(PlanillaService::class)->ultimaRevision($equipo->id));

        return new EquipoResource($equipo);
    }

    public function update(ActualizarEquipoRequest $solicitud, Equipo $equipo): EquipoResource
    {
        $this->authorize('update', $equipo);
        $actualizado = $this->servicioEquipo->actualizar($equipo, $solicitud->validated());

        return new EquipoResource($actualizado);
    }

    public function destroy(Equipo $equipo): JsonResponse
    {
        $this->authorize('delete', $equipo);
        $this->servicioEquipo->eliminar($equipo);

        return response()->json([
            'exito' => true,
            'mensaje' => 'Equipo eliminado correctamente',
        ]);
    }

    public function historial(Equipo $equipo): AnonymousResourceCollection
    {
        $this->authorize('verHistorial', $equipo);
        $historial = $equipo->historial()
            ->with('usuario')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return HistorialEquipoResource::collection($historial);
    }

    public function agregarHistorial(RegistrarHistorialEquipoRequest $solicitud, Equipo $equipo): JsonResponse
    {
        $this->authorize('agregarHistorial', $equipo);
        $historial = $this->servicioEquipo->agregarHistorial($equipo, $solicitud->validated());

        return (new HistorialEquipoResource($historial))
            ->response()
            ->setStatusCode(201);
    }
}
