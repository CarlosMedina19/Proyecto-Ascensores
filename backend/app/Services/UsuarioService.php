<?php

namespace App\Services;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UsuarioService
{
    /** Consulta con los filtros del listado (también la usa la exportación a Excel). */
    public function consulta(array $filtros = []): Builder
    {
        $consulta = Usuario::with(['rol', 'cliente']);

        if (! empty($filtros['rol_id'])) {
            $consulta->where('rol_id', $filtros['rol_id']);
        }

        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
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

        return $consulta;
    }

    public function listar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        return $this->consulta($filtros)->orderBy('nombre')->paginate($porPagina, ['*'], 'pagina');
    }

    public function crear(array $datos): Usuario
    {
        $usuario = DB::transaction(function () use ($datos) {
            $datos['activo'] = $datos['activo'] ?? true;
            $datos['cliente_id'] = $this->clienteSegunRol($datos['rol_id'], $datos['cliente_id'] ?? null);

            $usuario = Usuario::create($datos);
            RegistroAuditoriaService::registrar('creado', $usuario, $usuario->toArray());

            return $usuario;
        });

        $usuario->enviarVerificacionCorreo();

        return $usuario->load(['rol', 'cliente']);
    }

    public function actualizar(Usuario $usuario, array $datos): Usuario
    {
        return DB::transaction(function () use ($usuario, $datos) {
            if (empty($datos['password'])) {
                unset($datos['password']);
            }

            if (array_key_exists('rol_id', $datos) || array_key_exists('cliente_id', $datos)) {
                $datos['cliente_id'] = $this->clienteSegunRol(
                    $datos['rol_id'] ?? $usuario->rol_id,
                    array_key_exists('cliente_id', $datos) ? $datos['cliente_id'] : $usuario->cliente_id
                );
            }

            $usuario->update($datos);
            $cambios = $usuario->getChanges();

            // Un usuario desactivado o con nueva contraseña pierde sus sesiones abiertas (RF-003).
            if (array_key_exists('password', $cambios) || ($usuario->wasChanged('activo') && ! $usuario->activo)) {
                $usuario->tokens()->delete();
            }

            RegistroAuditoriaService::registrar('actualizado', $usuario, $cambios);

            return $usuario->fresh(['rol', 'cliente']);
        });
    }

    public function alternarEstado(Usuario $usuario): Usuario
    {
        return DB::transaction(function () use ($usuario) {
            $usuario->update(['activo' => ! $usuario->activo]);

            if (! $usuario->activo) {
                $usuario->tokens()->delete();
            }

            RegistroAuditoriaService::registrar($usuario->activo ? 'activado' : 'desactivado', $usuario, ['activo' => $usuario->activo]);

            return $usuario->fresh(['rol', 'cliente']);
        });
    }

    /**
     * Solo los usuarios con rol cliente quedan asociados a un cliente.
     */
    private function clienteSegunRol(int|string $rolId, int|string|null $clienteId): ?int
    {
        return Rol::whereKey($rolId)->value('nombre') === 'cliente' && $clienteId !== null
            ? (int) $clienteId
            : null;
    }
}
