<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Edificio;
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

        if ($usuarioActual instanceof Usuario && $usuarioActual->esCliente()) {
            $consulta->whereHas('edificio', fn ($consulta) => $consulta->where('cliente_id', $usuarioActual->cliente_id));
        }

        if (! empty($filtros['edificio_id'])) {
            $consulta->where('edificio_id', $filtros['edificio_id']);
        }

        if (! empty($filtros['cliente_id'])) {
            $consulta->whereHas('edificio', fn ($consulta) => $consulta->where('cliente_id', $filtros['cliente_id']));
        }

        if (! empty($filtros['tipo_edificio'])) {
            $consulta->whereHas('edificio', fn ($consulta) => $consulta->where('tipo', $filtros['tipo_edificio']));
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

        return $consulta->orderBy('codigo')->paginate($porPagina, ['*'], 'pagina');
    }

    public function crear(array $datos): Equipo
    {
        $this->validarEdificioActivo($datos['edificio_id']);

        return DB::transaction(function () use ($datos) {
            $datosEquipo = collect($datos)->except(['ascensor'])->toArray();
            $datosEquipo['tipo'] = 'ascensor';
            $datosEquipo['ubicacion'] = Edificio::findOrFail($datos['edificio_id'])->ubicacionEquipos();
            $equipo = Equipo::create($datosEquipo);

            $equipo->ascensor()->create($datos['ascensor']);

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
        if (isset($datos['edificio_id']) && (int) $datos['edificio_id'] !== $equipo->edificio_id) {
            $this->validarEdificioActivo($datos['edificio_id']);
        }

        return DB::transaction(function () use ($equipo, $datos) {
            $datosEquipo = collect($datos)->except(['ascensor', 'tipo'])->toArray();
            if (isset($datos['edificio_id'])) {
                $datosEquipo['ubicacion'] = Edificio::findOrFail($datos['edificio_id'])->ubicacionEquipos();
            }
            $estadoAnterior = $equipo->estado;
            $equipo->update($datosEquipo);
            $cambios = $equipo->getChanges();

            if (! empty($datos['ascensor'])) {
                $ascensor = $equipo->ascensor()->updateOrCreate(['equipo_id' => $equipo->id], $datos['ascensor']);

                if ($ascensor->wasChanged() || $ascensor->wasRecentlyCreated) {
                    $cambios['ascensor'] = collect($ascensor->getChanges())->except('updated_at')->all();
                    $equipo->historial()->create([
                        'usuario_id' => Auth::id(),
                        'evento' => 'ficha_tecnica_actualizada',
                        'descripcion' => 'Se actualizó la ficha técnica del ascensor.',
                    ]);
                }
            }

            if ($equipo->estado !== $estadoAnterior) {
                $equipo->historial()->create([
                    'usuario_id' => Auth::id(),
                    'evento' => 'cambio_estado',
                    'descripcion' => "Estado actualizado de {$estadoAnterior} a {$equipo->estado}.",
                ]);
            }

            RegistroAuditoriaService::registrar('actualizado', $equipo, $cambios);

            return $equipo->fresh(['edificio.cliente', 'ascensor']);
        });
    }

    public function eliminar(Equipo $equipo): void
    {
        if ($equipo->asignacionActiva()->exists()) {
            throw new ReglaNegocioException('El ascensor tiene un plan de mantenimiento activo. Finalice la asignación antes de darlo de baja.');
        }

        DB::transaction(function () use ($equipo) {
            $equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'baja_equipo',
                'descripcion' => 'Equipo dado de baja del sistema.',
            ]);
            $equipo->delete();
            RegistroAuditoriaService::registrar('eliminado', $equipo, []);
        });
    }

    public function agregarHistorial(Equipo $equipo, array $datos): HistorialEquipo
    {
        return DB::transaction(function () use ($equipo, $datos) {
            $historial = $equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => $datos['evento'],
                'descripcion' => $datos['descripcion'] ?? null,
            ]);

            RegistroAuditoriaService::registrar('creado', $historial, $historial->toArray());

            return $historial->load('usuario');
        });
    }

    private function validarEdificioActivo(int|string $edificioId): void
    {
        if (! Edificio::whereKey($edificioId)->where('activo', true)->exists()) {
            throw new ReglaNegocioException('No se pueden registrar equipos en un edificio inactivo.');
        }
    }
}
