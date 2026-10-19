<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contrato\ActualizarContratoRequest;
use App\Http\Requests\Contrato\GuardarContratoRequest;
use App\Http\Requests\Contrato\RenovarContratoRequest;
use App\Http\Requests\Contrato\SeguimientoRenovacionRequest;
use App\Http\Requests\MotivoRequest;
use App\Http\Resources\ContratoResource;
use App\Http\Resources\SeguimientoRenovacionResource;
use App\Models\Contrato;
use App\Services\ContratoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * RF-019, RF-020 y RF-021: contratos, seguimiento de renovación y alertas de vencimiento.
 */
class ContratoController extends Controller
{
    public function __construct(
        protected ContratoService $servicioContrato
    ) {}

    public function index(Request $solicitud): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contrato::class);

        return ContratoResource::collection(
            $this->servicioContrato->listar($solicitud->all(), $this->porPagina($solicitud))
        );
    }

    public function store(GuardarContratoRequest $solicitud): JsonResponse
    {
        $this->authorize('create', Contrato::class);
        $contrato = $this->servicioContrato->crear($solicitud->validated());

        return (new ContratoResource($this->detalle($contrato)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Contrato $contrato): ContratoResource
    {
        $this->authorize('view', $contrato);

        return new ContratoResource($this->detalle($contrato));
    }

    public function update(ActualizarContratoRequest $solicitud, Contrato $contrato): ContratoResource
    {
        $this->authorize('update', $contrato);

        return new ContratoResource($this->detalle(
            $this->servicioContrato->actualizar($contrato, $solicitud->validated())
        ));
    }

    public function registrarSeguimiento(SeguimientoRenovacionRequest $solicitud, Contrato $contrato): JsonResponse
    {
        $this->authorize('renovar', $contrato);
        $seguimiento = $this->servicioContrato->registrarSeguimiento(
            $contrato,
            $solicitud->validated('resultado'),
            $solicitud->validated('observacion')
        );

        return (new SeguimientoRenovacionResource($seguimiento->load('usuario')))
            ->response()
            ->setStatusCode(201);
    }

    public function renovar(RenovarContratoRequest $solicitud, Contrato $contrato): JsonResponse
    {
        $this->authorize('renovar', $contrato);
        $nuevo = $this->servicioContrato->renovar($contrato, $solicitud->validated());

        return (new ContratoResource($this->detalle($nuevo)))
            ->additional(['mensaje' => "Contrato renovado como {$nuevo->numero}"])
            ->response()
            ->setStatusCode(201);
    }

    public function cancelar(MotivoRequest $solicitud, Contrato $contrato): ContratoResource
    {
        $this->authorize('update', $contrato);

        return new ContratoResource($this->detalle(
            $this->servicioContrato->cancelar($contrato, $solicitud->validated('motivo'))
        ));
    }

    private function detalle(Contrato $contrato): Contrato
    {
        return $contrato->load([
            'cliente',
            'contratoAnterior',
            'equipos.edificio',
            'seguimientos' => fn ($c) => $c->with('usuario')->latest('created_at')->latest('id'),
            'asignaciones' => fn ($c) => $c->with(['plan', 'periodicidad', 'equipo'])->orderByDesc('fecha_inicio'),
        ]);
    }
}
