<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permiso;
use Illuminate\Http\JsonResponse;

class PermisoController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Permiso::class);

        $permisos = Permiso::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'descripcion']);

        return response()->json([
            'datos' => $permisos->map(fn (Permiso $permiso): array => [
                'id' => $permiso->id,
                'nombre' => $permiso->nombre,
                'descripcion' => $permiso->descripcion,
            ]),
        ]);
    }
}
