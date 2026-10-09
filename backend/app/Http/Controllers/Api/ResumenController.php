<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;

class ResumenController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('verResumen');

        return response()->json([
            'exito' => true,
            'mensaje' => 'Dashboard FASE 1',
            'datos' => [
                'usuarios' => [
                    'total' => Usuario::count(),
                    'activo' => Usuario::where('activo', true)->count(),
                ],
                'clientes' => [
                    'total' => Cliente::count(),
                    'activo' => Cliente::where('estado', true)->count(),
                ],
                'edificios' => [
                    'total' => Edificio::count(),
                ],
                'equipos' => [
                    'total' => Equipo::count(),
                    'activo' => Equipo::where('estado', 'activo')->count(),
                    'inactivo' => Equipo::where('estado', 'inactivo')->count(),
                    'mantenimiento' => Equipo::where('estado', 'mantenimiento')->count(),
                ],
            ],
            'generado_en' => now()->toIso8601String(),
        ]);
    }
}
