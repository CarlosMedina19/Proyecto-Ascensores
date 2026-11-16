<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Equipo\SubirFotosEquipoRequest;
use App\Http\Resources\FotoEquipoResource;
use App\Models\Equipo;
use App\Models\FotoEquipo;
use App\Services\FotoEquipoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fotos del ascensor (máximo 3).
 */
class FotoEquipoController extends Controller
{
    public function __construct(
        protected FotoEquipoService $servicioFotos
    ) {}

    public function index(Equipo $equipo): AnonymousResourceCollection
    {
        $this->authorize('view', $equipo);

        return FotoEquipoResource::collection($equipo->fotos()->get());
    }

    public function store(SubirFotosEquipoRequest $solicitud, Equipo $equipo): JsonResponse
    {
        $this->authorize('gestionarFotos', $equipo);
        $fotos = $this->servicioFotos->agregar($equipo, $solicitud->file('fotos'));

        return FotoEquipoResource::collection($fotos)
            ->additional(['mensaje' => $fotos->count() === 1 ? 'Foto agregada.' : 'Fotos agregadas.'])
            ->response()
            ->setStatusCode(201);
    }

    public function archivo(Equipo $equipo, FotoEquipo $foto): StreamedResponse
    {
        $this->authorize('view', $equipo);
        abort_unless($foto->equipo_id === $equipo->id && $this->servicioFotos->existe($foto), 404);

        return $this->servicioFotos->responder($foto);
    }

    public function destroy(Equipo $equipo, FotoEquipo $foto): JsonResponse
    {
        $this->authorize('gestionarFotos', $equipo);
        abort_unless($foto->equipo_id === $equipo->id, 404);
        $this->servicioFotos->eliminar($foto);

        return response()->json(['mensaje' => 'Foto eliminada.']);
    }
}
