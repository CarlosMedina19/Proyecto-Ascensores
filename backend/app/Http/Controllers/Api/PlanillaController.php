<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MotivoRequest;
use App\Http\Requests\Planilla\CorrectivoDerivadoRequest;
use App\Http\Requests\Planilla\FirmaRequest;
use App\Http\Requests\Planilla\FotoPlanillaRequest;
use App\Http\Requests\Planilla\GuardarPlanillaRequest;
use App\Http\Resources\FirmaOrdenResource;
use App\Http\Resources\InformeOrdenResource;
use App\Http\Resources\OrdenTrabajoResource;
use App\Http\Resources\PlanillaResource;
use App\Models\FirmaOrden;
use App\Models\InformeOrden;
use App\Models\OrdenTrabajo;
use App\Models\PlanillaFoto;
use App\Models\PlanillaResultado;
use App\Services\InformeService;
use App\Services\OrdenTrabajoService;
use App\Services\PlanillaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Fase 4 (RF-030 a RF-039): planilla digital, fotos, firmas, correctivo derivado e informe PDF.
 */
class PlanillaController extends Controller
{
    public function __construct(
        protected PlanillaService $planillas,
        protected InformeService $informes,
        protected OrdenTrabajoService $ordenes,
    ) {}

    /** Ítems de las seis secciones (catálogo). */
    public function catalogo(): JsonResponse
    {
        return response()->json([
            'datos' => $this->planillas->catalogo()->map(fn ($i) => [
                'id' => $i->id,
                'seccion' => $i->seccion,
                'codigo' => $i->codigo,
                'nombre' => $i->nombre,
                'critico' => $i->critico,
            ])->values(),
        ]);
    }

    public function show(OrdenTrabajo $orden): JsonResponse
    {
        $this->authorize('view', $orden);
        $planilla = $orden->planilla;

        return response()->json([
            'datos' => $planilla ? (new PlanillaResource($this->planillas->detalle($planilla)))->resolve() : null,
            'faltantes' => $this->planillas->faltantes($planilla),
        ]);
    }

    public function guardar(GuardarPlanillaRequest $solicitud, OrdenTrabajo $orden): JsonResponse
    {
        $this->authorize('ejecutar', $orden);
        $planilla = $this->planillas->guardar($orden, $solicitud->validated());

        return response()->json([
            'datos' => (new PlanillaResource($planilla))->resolve(),
            'faltantes' => $this->planillas->faltantes($planilla),
            'mensaje' => 'Planilla guardada.',
        ]);
    }

    public function agregarFoto(FotoPlanillaRequest $solicitud, OrdenTrabajo $orden): JsonResponse
    {
        $this->authorize('ejecutar', $orden);
        $foto = $this->planillas->agregarFoto($orden, $solicitud->file('foto'), $solicitud->validated('item_id'), $solicitud->validated('descripcion'));

        return response()->json(['datos' => ['id' => $foto->id, 'ruta_archivo' => "/ordenes/{$orden->id}/planilla/fotos/{$foto->id}/archivo"], 'mensaje' => 'Foto agregada.'], 201);
    }

    public function foto(OrdenTrabajo $orden, PlanillaFoto $foto): BinaryFileResponse
    {
        $this->authorize('view', $orden);
        abort_unless($foto->planilla?->orden_id === $orden->id, 404);

        return $this->archivo($foto->ruta, $foto->tipo_mime);
    }

    public function eliminarFoto(OrdenTrabajo $orden, PlanillaFoto $foto): JsonResponse
    {
        $this->authorize('ejecutar', $orden);
        abort_unless($foto->planilla?->orden_id === $orden->id, 404);
        $this->planillas->eliminarFoto($orden, $foto);

        return response()->json(['mensaje' => 'Foto eliminada.']);
    }

    public function firmar(FirmaRequest $solicitud, OrdenTrabajo $orden): JsonResponse
    {
        $this->authorize('ejecutar', $orden);
        $firma = $this->planillas->firmar($orden, $solicitud->validated());

        return (new FirmaOrdenResource($firma))->additional(['mensaje' => 'Firma registrada.'])->response()->setStatusCode(201);
    }

    public function firma(OrdenTrabajo $orden, FirmaOrden $firma): BinaryFileResponse
    {
        $this->authorize('view', $orden);
        abort_unless($firma->orden_id === $orden->id, 404);

        return $this->archivo($firma->ruta, 'image/png');
    }

    /** RF-038: orden correctiva vinculada a un ítem en R o M. */
    public function crearCorrectivo(CorrectivoDerivadoRequest $solicitud, OrdenTrabajo $orden, PlanillaResultado $resultado): JsonResponse
    {
        $this->authorize('crearCorrectivo', $orden);
        if ($solicitud->filled('tecnicos')) {
            $this->authorize('asignar', $orden);
        }
        $correctivo = $this->planillas->crearCorrectivo($orden, $resultado, $solicitud->validated(), $this->ordenes);

        return (new OrdenTrabajoResource($correctivo))
            ->additional(['mensaje' => "Correctivo {$correctivo->numero} creado."])
            ->response()
            ->setStatusCode(201);
    }

    public function informes(OrdenTrabajo $orden): AnonymousResourceCollection
    {
        $this->authorize('view', $orden);

        return InformeOrdenResource::collection($orden->informes()->with('generador')->get());
    }

    /** RF-039: una corrección genera una versión nueva del informe (las anteriores no cambian). */
    public function nuevaVersion(MotivoRequest $solicitud, OrdenTrabajo $orden): JsonResponse
    {
        $this->authorize('update', $orden);
        $informe = $this->informes->generar($orden, $solicitud->validated('motivo'));

        return (new InformeOrdenResource($informe->load('generador')))->response()->setStatusCode(201);
    }

    public function pdf(OrdenTrabajo $orden, InformeOrden $informe): BinaryFileResponse
    {
        $this->authorize('view', $orden);
        abort_unless($informe->orden_id === $orden->id, 404);

        return response()->file($this->informes->rutaAbsoluta($informe), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"Informe-{$orden->numero}-v{$informe->version}.pdf\"",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function archivo(string $ruta, string $tipo): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($ruta), 404);

        return response()->file(Storage::disk('local')->path($ruta), [
            'Content-Type' => $tipo,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
