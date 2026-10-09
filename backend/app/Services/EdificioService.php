<?php

namespace App\Services;

use App\Models\Edificio;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EdificioService
{
    public function listar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $consulta = Edificio::with(['cliente'])->withCount('equipos');
        $usuarioActual = Auth::user();

        if ($usuarioActual instanceof Usuario && $usuarioActual->tieneRol('cliente')) {
            $consulta->where('cliente_id', $usuarioActual->cliente_id);
        }

        $coincidencia = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        if (! empty($filtros['cliente_id'])) {
            $consulta->where('cliente_id', $filtros['cliente_id']);
        }

        if (! empty($filtros['ciudad'])) {
            $consulta->where('ciudad', $coincidencia, "%{$filtros['ciudad']}%");
        }

        if (! empty($filtros['busqueda'])) {
            $busqueda = $filtros['busqueda'];
            $consulta->where(function ($consulta) use ($busqueda, $coincidencia) {
                $consulta->where('nombre', $coincidencia, "%{$busqueda}%")
                    ->orWhere('direccion', $coincidencia, "%{$busqueda}%")
                    ->orWhere('ciudad', $coincidencia, "%{$busqueda}%");
            });
        }

        return $consulta->latest()->paginate($porPagina);
    }

    public function crear(array $datos): Edificio
    {
        return DB::transaction(function () use ($datos) {
            $edificio = Edificio::create($datos);
            RegistroAuditoriaService::registrar('creado', $edificio, $edificio->toArray());

            return $edificio->load('cliente');
        });
    }

    public function actualizar(Edificio $edificio, array $datos): Edificio
    {
        return DB::transaction(function () use ($edificio, $datos) {
            $edificio->update($datos);
            RegistroAuditoriaService::registrar('actualizado', $edificio, $edificio->getChanges());

            return $edificio->fresh(['cliente']);
        });
    }

    public function eliminar(Edificio $edificio): void
    {
        DB::transaction(function () use ($edificio) {
            $edificio->delete();
            RegistroAuditoriaService::registrar('eliminado', $edificio);
        });
    }
}
