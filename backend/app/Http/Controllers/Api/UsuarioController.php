<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usuario\ActualizarUsuarioRequest;
use App\Http\Requests\Usuario\GuardarUsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UsuarioController extends Controller
{
    public function __construct(
        protected UsuarioService $servicioUsuario
    ) {}

    public function index(Request $solicitud): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Usuario::class);
        $usuarios = $this->servicioUsuario->listar($solicitud->all(), (int) $solicitud->input('por_pagina', 15));

        return UsuarioResource::collection($usuarios);
    }

    public function store(GuardarUsuarioRequest $solicitud): JsonResponse
    {
        $this->authorize('create', Usuario::class);
        $usuario = $this->servicioUsuario->crear($solicitud->validated());

        return (new UsuarioResource($usuario))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Usuario $usuario): UsuarioResource
    {
        $this->authorize('view', $usuario);

        return new UsuarioResource($usuario->load('rol'));
    }

    public function update(ActualizarUsuarioRequest $solicitud, Usuario $usuario): UsuarioResource
    {
        $this->authorize('update', $usuario);
        $actualizado = $this->servicioUsuario->actualizar($usuario, $solicitud->validated());

        return new UsuarioResource($actualizado);
    }

    public function alternarEstado(Usuario $usuario): UsuarioResource
    {
        $this->authorize('alternarEstado', $usuario);
        $actualizado = $this->servicioUsuario->alternarEstado($usuario);

        return new UsuarioResource($actualizado);
    }
}
