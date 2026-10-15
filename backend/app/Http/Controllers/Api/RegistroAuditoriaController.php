<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegistroAuditoriaResource;
use App\Models\RegistroAuditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class RegistroAuditoriaController extends Controller
{
    public function index(Request $solicitud): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RegistroAuditoria::class);

        $consulta = RegistroAuditoria::with('usuario');

        if ($solicitud->filled('accion')) {
            $consulta->where('accion', $solicitud->input('accion'));
        }

        if ($solicitud->filled('modelo')) {
            $coincidencia = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $consulta->where('modelo', $coincidencia, '%'.$solicitud->input('modelo').'%');
        }

        if ($solicitud->filled('usuario_id')) {
            $consulta->where('usuario_id', $solicitud->input('usuario_id'));
        }

        $porPagina = max(1, min((int) $solicitud->input('por_pagina', 20), 100));
        $logs = $consulta->latest('created_at')->paginate($porPagina);

        return RegistroAuditoriaResource::collection($logs);
    }
}
