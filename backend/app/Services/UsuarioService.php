<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioService
{
    public function listar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $consulta = Usuario::with('rol');

        if (! empty($filtros['rol_id'])) {
            $consulta->where('rol_id', $filtros['rol_id']);
        }

        if (isset($filtros['activo'])) {
            $consulta->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filtros['busqueda'])) {
            $busqueda = $filtros['busqueda'];
            $coincidencia = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $consulta->where(function ($consulta) use ($busqueda, $coincidencia) {
                $consulta->where('nombre', $coincidencia, "%{$busqueda}%")
                    ->orWhere('correo', $coincidencia, "%{$busqueda}%");
            });
        }

        return $consulta->latest()->paginate($porPagina);
    }

    public function crear(array $datos): Usuario
    {
        return DB::transaction(function () use ($datos) {
            if (isset($datos['password'])) {
                $datos['password'] = Hash::make($datos['password']);
            }
            $datos['activo'] = $datos['activo'] ?? true;

            $usuario = Usuario::create($datos);
            RegistroAuditoriaService::registrar('creado', $usuario, collect($usuario->toArray())->except('password')->toArray());

            return $usuario->load('rol');
        });
    }

    public function actualizar(Usuario $usuario, array $datos): Usuario
    {
        return DB::transaction(function () use ($usuario, $datos) {
            if (! empty($datos['password'])) {
                $datos['password'] = Hash::make($datos['password']);
            } else {
                unset($datos['password']);
            }

            $usuario->update($datos);
            RegistroAuditoriaService::registrar('actualizado', $usuario, collect($usuario->getChanges())->except('password')->toArray());

            return $usuario->fresh(['rol']);
        });
    }

    public function alternarEstado(Usuario $usuario): Usuario
    {
        return DB::transaction(function () use ($usuario) {
            $usuario->update(['activo' => ! $usuario->activo]);
            RegistroAuditoriaService::registrar('actualizado', $usuario, ['activo' => $usuario->activo]);

            return $usuario->fresh(['rol']);
        });
    }
}
