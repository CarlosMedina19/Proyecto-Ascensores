<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conjunto\GuardarConjuntoRequest;
use App\Http\Resources\ConjuntoResource;
use App\Models\Conjunto;
use App\Services\ConjuntoService;
use App\Support\Colombia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConjuntoController extends Controller
{
    public function __construct(private readonly ConjuntoService $conjuntos) {}

    public function index(Request $solicitud): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Conjunto::class);

        return ConjuntoResource::collection($this->conjuntos->listar($solicitud->all(), $this->porPagina($solicitud)));
    }

    public function store(GuardarConjuntoRequest $solicitud): JsonResponse
    {
        $this->authorize('create', Conjunto::class);

        return (new ConjuntoResource($this->conjuntos->crear($solicitud->validated())))->response()->setStatusCode(201);
    }

    public function show(Conjunto $conjunto): ConjuntoResource
    {
        $this->authorize('view', $conjunto);

        return new ConjuntoResource($conjunto->load([
            'cliente',
            'edificios' => fn ($c) => $c->withCount('equipos')->with(['equipos' => fn ($e) => $e->orderBy('codigo')])->orderBy('nombre'),
        ])->loadCount(['edificios', 'equipos']));
    }

    public function update(GuardarConjuntoRequest $solicitud, Conjunto $conjunto): ConjuntoResource
    {
        $this->authorize('update', $conjunto);

        return new ConjuntoResource($this->conjuntos->actualizar($conjunto, $solicitud->validated()));
    }

    public function destroy(Conjunto $conjunto): JsonResponse
    {
        $this->authorize('delete', $conjunto);
        $this->conjuntos->eliminar($conjunto);

        return response()->json(['exito' => true, 'mensaje' => 'Conjunto eliminado.']);
    }

    /** Departamentos y municipios de Colombia para los buscadores de ubicación. */
    public function ubicaciones(): JsonResponse
    {
        return response()->json(['datos' => Colombia::datos()])->setMaxAge(86400)->setPublic();
    }
}
