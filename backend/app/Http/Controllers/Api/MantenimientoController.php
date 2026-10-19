<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mantenimiento\EjecutarMantenimientoRequest;
use App\Http\Requests\Mantenimiento\ListarMantenimientosRequest;
use App\Http\Requests\Mantenimiento\ReprogramarMantenimientoRequest;
use App\Http\Requests\MotivoRequest;
use App\Http\Resources\MantenimientoProgramadoResource;
use App\Models\MantenimientoProgramado;
use App\Services\MantenimientoService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * RF-017 y RF-018: calendario de mantenimientos preventivos.
 */
class MantenimientoController extends Controller
{
    public function __construct(
        protected MantenimientoService $servicioMantenimiento
    ) {}

    /** El calendario se consulta por rango de fechas, sin paginar. */
    public function index(ListarMantenimientosRequest $solicitud): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MantenimientoProgramado::class);

        return MantenimientoProgramadoResource::collection(
            $this->servicioMantenimiento->listar($solicitud->validated())
        );
    }

    public function show(MantenimientoProgramado $mantenimiento): MantenimientoProgramadoResource
    {
        $this->authorize('view', $mantenimiento);

        return new MantenimientoProgramadoResource($this->detalle($mantenimiento));
    }

    public function reprogramar(ReprogramarMantenimientoRequest $solicitud, MantenimientoProgramado $mantenimiento): MantenimientoProgramadoResource
    {
        $this->authorize('update', $mantenimiento);

        return new MantenimientoProgramadoResource($this->detalle(
            $this->servicioMantenimiento->reprogramar($mantenimiento, $solicitud->validated('fecha'), $solicitud->validated('motivo'), $solicitud->validated('hora'))
        ));
    }

    public function cancelar(MotivoRequest $solicitud, MantenimientoProgramado $mantenimiento): MantenimientoProgramadoResource
    {
        $this->authorize('update', $mantenimiento);

        return new MantenimientoProgramadoResource($this->detalle(
            $this->servicioMantenimiento->cancelar($mantenimiento, $solicitud->validated('motivo'))
        ));
    }

    public function ejecutar(EjecutarMantenimientoRequest $solicitud, MantenimientoProgramado $mantenimiento): MantenimientoProgramadoResource
    {
        $this->authorize('update', $mantenimiento);

        return new MantenimientoProgramadoResource($this->detalle(
            $this->servicioMantenimiento->ejecutar($mantenimiento, $solicitud->validated('fecha_ejecucion'), $solicitud->validated('observacion'))
        ));
    }

    private function detalle(MantenimientoProgramado $mantenimiento): MantenimientoProgramado
    {
        return $mantenimiento->load(['equipo.edificio.cliente', 'asignacion.plan', 'asignacion.periodicidad', 'ordenActiva']);
    }
}
