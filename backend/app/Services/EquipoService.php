<?php

namespace App\Services;

use App\Models\Ascensor;
use App\Models\Equipo;
use App\Models\HistorialEquipo;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EquipoService
{
    public function listar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $consulta = Equipo::with(['edificio.cliente', 'ascensor']);
        $usuarioActual = Auth::user();

        if ($usuarioActual instanceof Usuario && $usuarioActual->tieneRol('cliente')) {
            $consulta->whereHas('edificio', fn ($consulta) => $consulta->where('cliente_id', $usuarioActual->cliente_id));
        }

        if (! empty($filtros['edificio_id'])) {
            $consulta->where('edificio_id', $filtros['edificio_id']);
        }

        if (! empty($filtros['tipo'])) {
            $consulta->where('tipo', $filtros['tipo']);
        }

        if (! empty($filtros['estado'])) {
            $consulta->where('estado', $filtros['estado']);
        }

        if (! empty($filtros['busqueda'])) {
            $busqueda = $filtros['busqueda'];
            $coincidencia = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $consulta->where(function ($consulta) use ($busqueda, $coincidencia) {
                $consulta->where('codigo', $coincidencia, "%{$busqueda}%")
                    ->orWhere('marca', $coincidencia, "%{$busqueda}%")
                    ->orWhere('modelo', $coincidencia, "%{$busqueda}%")
                    ->orWhere('numero_serie', $coincidencia, "%{$busqueda}%")
                    ->orWhere('ubicacion', $coincidencia, "%{$busqueda}%");
            });
        }

        return $consulta->latest()->paginate($porPagina);
    }

    public function crear(array $datos): Equipo
    {
        return DB::transaction(function () use ($datos) {
            $datosEquipo = collect($datos)->except(['ascensor'])->toArray();
            $equipo = Equipo::create($datosEquipo);

            if (! empty($datos['ascensor'])) {
                $datosAscensor = array_merge($datos['ascensor'], ['equipo_id' => $equipo->id]);
                Ascensor::create($datosAscensor);
            }

            $equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'alta_equipo',
                'descripcion' => "Ascensor registrado con código {$equipo->codigo}.",
            ]);

            RegistroAuditoriaService::registrar('creado', $equipo, $equipo->toArray());

            return $equipo->load(['edificio.cliente', 'ascensor']);
        });
    }

    public function actualizar(Equipo $equipo, array $datos): Equipo
    {
        return DB::transaction(function () use ($equipo, $datos) {
            $datosEquipo = collect($datos)->except(['ascensor'])->toArray();
            $estadoAnterior = $equipo->estado;
            $equipo->update($datosEquipo);

            if (isset($datos['ascensor'])) {
                $equipo->ascensor()->updateOrCreate(
                    ['equipo_id' => $equipo->id],
                    $datos['ascensor']
                );
            }

            if ($equipo->estado !== $estadoAnterior) {
                $equipo->historial()->create([
                    'usuario_id' => Auth::id(),
                    'evento' => 'cambio_estado',
                    'descripcion' => "Estado actualizado de {$estadoAnterior} a {$equipo->estado}.",
                ]);
            }

            RegistroAuditoriaService::registrar('actualizado', $equipo, $equipo->getChanges());

            return $equipo->fresh(['edificio.cliente', 'ascensor', 'historial']);
        });
    }

    public function eliminar(Equipo $equipo): void
    {
        DB::transaction(function () use ($equipo) {
            $equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'baja_equipo',
                'descripcion' => 'Equipo dado de baja del sistema.',
            ]);
            $equipo->delete();
            RegistroAuditoriaService::registrar('eliminado', $equipo);
        });
    }

    public function agregarHistorial(Equipo $equipo, array $datos): HistorialEquipo
    {
        $historial = $equipo->historial()->create([
            'usuario_id' => Auth::id(),
            'evento' => $datos['evento'],
            'descripcion' => $datos['descripcion'] ?? null,
        ]);

        RegistroAuditoriaService::registrar('creado', $historial, $historial->toArray());

        return $historial->load('usuario');
    }
}
