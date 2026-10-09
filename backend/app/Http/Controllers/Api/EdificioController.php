<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Edificio\ActualizarEdificioRequest;
use App\Http\Requests\Edificio\GuardarEdificioRequest;
use App\Http\Resources\EdificioResource;
use App\Models\Edificio;
use App\Services\EdificioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EdificioController extends Controller
{
    public function __construct(
        protected EdificioService $servicioEdificio
    ) {}

    public function index(Request $solicitud): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Edificio::class);
        $edificios = $this->servicioEdificio->listar($solicitud->all(), (int) $solicitud->input('por_pagina', 15));

        return EdificioResource::collection($edificios);
    }

    public function store(GuardarEdificioRequest $solicitud): JsonResponse
    {
        $this->authorize('create', Edificio::class);
        $edificio = $this->servicioEdificio->crear($solicitud->validated());

        return (new EdificioResource($edificio))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Edificio $edificio): EdificioResource
    {
        $this->authorize('view', $edificio);

        return new EdificioResource($edificio->load(['cliente', 'equipos.ascensor']));
    }

    public function update(ActualizarEdificioRequest $solicitud, Edificio $edificio): EdificioResource
    {
        $this->authorize('update', $edificio);
        $actualizado = $this->servicioEdificio->actualizar($edificio, $solicitud->validated());

        return new EdificioResource($actualizado);
    }

    public function destroy(Edificio $edificio): JsonResponse
    {
        $this->authorize('delete', $edificio);
        $this->servicioEdificio->eliminar($edificio);

        return response()->json([
            'exito' => true,
            'mensaje' => 'Edificio eliminado correctamente',
        ]);
    }
}
