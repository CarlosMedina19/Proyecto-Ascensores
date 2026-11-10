<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rol\ActualizarRolRequest;
use App\Http\Requests\Rol\GuardarRolRequest;
use App\Http\Requests\Rol\SincronizarPermisosRolRequest;
use App\Http\Resources\RolResource;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\RegistroAuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /** Solo el administrador: el correo de la cuenta principal de cada rol (la activa más antigua). */
    public function credenciales(Request $solicitud): JsonResponse
    {
        $this->exigirAdministrador($solicitud);

        $datos = Rol::orderBy('id')->get()->map(fn (Rol $rol) => [
            'rol_id' => $rol->id,
            'correo' => $this->cuentaPrincipal($rol)?->correo,
            'tiene_contrasena' => filled($this->cuentaPrincipal($rol)?->contrasena_cifrada),
        ]);

        return response()->json(['datos' => $datos]);
    }

    /** Solo el administrador: revela la contraseña de la cuenta principal del rol. Queda en auditoría. */
    public function credencial(Request $solicitud, Rol $rol): JsonResponse
    {
        $this->exigirAdministrador($solicitud);
        $usuario = $this->cuentaPrincipal($rol);
        if ($usuario) {
            RegistroAuditoriaService::registrar('credencial_consultada', $usuario, ['rol' => $rol->nombre, 'correo' => $usuario->correo], modulo: 'roles');
        }

        return response()->json(['datos' => [
            'rol_id' => $rol->id,
            'correo' => $usuario?->correo,
            'contrasena' => $usuario?->contrasenaVisible(),
        ]]);
    }

    private function exigirAdministrador(Request $solicitud): void
    {
        abort_unless($solicitud->user()?->tieneRol('administrador'), 403, 'Solo el administrador consulta las credenciales.');
    }

    private function cuentaPrincipal(Rol $rol): ?Usuario
    {
        return $rol->usuarios()->where('activo', true)->orderBy('id')->first();
    }
}
