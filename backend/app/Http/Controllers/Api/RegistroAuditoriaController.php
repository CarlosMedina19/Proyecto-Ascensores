<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegistroAuditoriaResource;
use App\Models\RegistroAuditoria;
use App\Services\RegistroAuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * RF-004: consulta de la auditoría (solo lectura, solo administrador).
 */
class RegistroAuditoriaController extends Controller
{
    public function index(Request $solicitud): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RegistroAuditoria::class);

        $solicitud->validate([
            'accion' => ['nullable', 'string', 'max:60'],
            'modulo' => ['nullable', 'string', 'max:60'],
            'usuario_id' => ['nullable', 'integer'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'recientes_horas' => ['nullable', 'integer', 'min:1', 'max:720'],
            'busqueda' => ['nullable', 'string', 'max:100'],
        ]);
        $busqueda = trim((string) $solicitud->input('busqueda'));

        $consulta = RegistroAuditoria::with('usuario')
            // Vista por defecto: solo los movimientos recientes; el resto se consulta con filtros.
            ->when($solicitud->filled('recientes_horas'), fn ($c) => $c->where('created_at', '>=', now()->subHours((int) $solicitud->input('recientes_horas'))))
            ->when($busqueda !== '', fn ($c) => $c->where(fn ($q) => $q
                ->where('ip', 'ilike', "%{$busqueda}%")
                ->orWhere('accion', 'ilike', "%{$busqueda}%")
                ->orWhereHas('usuario', fn ($u) => $u->where('nombre', 'ilike', "%{$busqueda}%")->orWhere('correo', 'ilike', "%{$busqueda}%"))))
            ->when($solicitud->filled('accion'), fn ($c) => $c->where('accion', $solicitud->input('accion')))
            ->when($solicitud->filled('modulo'), fn ($c) => $c->where('modulo', $solicitud->input('modulo')))
            ->when($solicitud->filled('usuario_id'), fn ($c) => $c->where('usuario_id', $solicitud->input('usuario_id')))
            ->when($solicitud->filled('fecha_desde'), fn ($c) => $c->where('created_at', '>=', $solicitud->date('fecha_desde')->startOfDay()))
            ->when($solicitud->filled('fecha_hasta'), fn ($c) => $c->where('created_at', '<=', $solicitud->date('fecha_hasta')->endOfDay()));

        $porPagina = max(1, min((int) $solicitud->input('por_pagina', 20), 100));

        return RegistroAuditoriaResource::collection(
            $consulta->orderByDesc('created_at')->orderByDesc('id')->paginate($porPagina, ['*'], 'pagina')
        );
    }

    public function modulos(): JsonResponse
    {
        $this->authorize('viewAny', RegistroAuditoria::class);

        return response()->json(['datos' => RegistroAuditoriaService::modulos()]);
    }
}
