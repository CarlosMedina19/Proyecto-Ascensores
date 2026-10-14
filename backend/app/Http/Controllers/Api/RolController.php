<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rol\ActualizarRolRequest;
use App\Http\Requests\Rol\GuardarRolRequest;
use App\Http\Requests\Rol\SincronizarPermisosRolRequest;
use App\Http\Resources\RolResource;
use App\Models\Permiso;
use App\Models\Rol;
use App\Services\RegistroAuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class RolController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Rol::class);
        $roles = Rol::with('permisos')->withCount('usuarios')->get();

        return RolResource::collection($roles);
    }

    public function show(Rol $rol): RolResource
    {
        $this->authorize('view', $rol);

        return new RolResource($rol->load('permisos')->loadCount('usuarios'));
    }

    public function store(GuardarRolRequest $solicitud): JsonResponse
    {
        $this->authorize('create', Rol::class);

        $rol = DB::transaction(function () use ($solicitud): Rol {
            $rol = Rol::create($solicitud->validated());
            RegistroAuditoriaService::registrar('creado', $rol, $rol->toArray());

            return $rol;
        });

        return (new RolResource($rol))
            ->response()
            ->setStatusCode(201);
    }

    public function update(ActualizarRolRequest $solicitud, Rol $rol): RolResource
    {
        $this->authorize('update', $rol);
        DB::transaction(function () use ($solicitud, $rol): void {
            $rol->update($solicitud->validated());
            RegistroAuditoriaService::registrar('actualizado', $rol, $rol->getChanges());
        });

        return new RolResource($rol->fresh()->load('permisos')->loadCount('usuarios'));
    }

    public function sincronizarPermisos(SincronizarPermisosRolRequest $solicitud, Rol $rol): RolResource
    {
        $this->authorize('sincronizarPermisos', $rol);

        DB::transaction(function () use ($solicitud, $rol): void {
            $nombresPermisos = $solicitud->validated('permisos');
            $idsPermisos = Permiso::query()
                ->whereIn('nombre', $nombresPermisos)
                ->pluck('id');

            $rol->permisos()->sync($idsPermisos);
            RegistroAuditoriaService::registrar('permisos_actualizados', $rol, ['permisos' => $nombresPermisos]);
        });

        return new RolResource($rol->fresh()->load('permisos')->loadCount('usuarios'));
    }
}
