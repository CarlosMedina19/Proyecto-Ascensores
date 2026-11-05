<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Conjunto;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Conjuntos, instituciones y residencias. Sus edificios heredan el cliente y el tipo.
 */
class ConjuntoService
{
    public function listar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $consulta = Conjunto::with('cliente')->withCount(['edificios', 'equipos']);
        $usuario = Auth::user();
        if ($usuario instanceof Usuario && $usuario->esCliente()) {
            $consulta->where('cliente_id', $usuario->cliente_id);
        }
        $como = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        foreach (['cliente_id', 'tipo'] as $campo) {
            if (! empty($filtros[$campo])) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
            $consulta->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN));
        }
        if (! empty($filtros['busqueda'])) {
            $texto = "%{$filtros['busqueda']}%";
            $consulta->where(fn ($c) => $c->where('nombre', $como, $texto)
                ->orWhere('direccion', $como, $texto)
                ->orWhere('ciudad', $como, $texto)
                ->orWhereHas('cliente', fn ($cl) => $cl->where('nombre', $como, $texto)));
        }

        return $consulta->orderBy('nombre')->paginate($porPagina, ['*'], 'pagina');
    }

    public function crear(array $datos): Conjunto
    {
        return DB::transaction(function () use ($datos) {
            $conjunto = Conjunto::create($datos);
            RegistroAuditoriaService::registrar('creado', $conjunto, $conjunto->toArray(), null, 'edificios');

            return $conjunto->load('cliente');
        });
    }

    public function actualizar(Conjunto $conjunto, array $datos): Conjunto
    {
        return DB::transaction(function () use ($conjunto, $datos) {
            $conjunto->update($datos);
            $cambios = $conjunto->getChanges();
            RegistroAuditoriaService::registrar('actualizado', $conjunto, $cambios, null, 'edificios');

            // Los edificios siguen al conjunto en cliente y tipo.
            $herencia = array_intersect_key($cambios, array_flip(['cliente_id', 'tipo']));
            if ($herencia) {
                $conjunto->edificios()->update($herencia);
            }

            return $conjunto->fresh(['cliente']);
        });
    }

    /** Un conjunto con edificios no se elimina; se desactiva. */
    public function eliminar(Conjunto $conjunto): void
    {
        if ($conjunto->edificios()->exists()) {
            throw new ReglaNegocioException('El conjunto tiene edificios. Muévalos a otro conjunto o desactívelo en lugar de eliminarlo.');
        }

        DB::transaction(function () use ($conjunto) {
            $conjunto->delete();
            RegistroAuditoriaService::registrar('eliminado', $conjunto, [], null, 'edificios');
        });
    }
}
