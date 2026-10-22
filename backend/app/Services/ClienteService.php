<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Cliente;
use App\Models\ContactoCliente;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClienteService
{
    /** Consulta con los filtros del listado (también la usa la exportación a Excel). */
    public function consulta(array $filtros = []): Builder
    {
        $consulta = Cliente::with(['contactos'])->withCount('edificios');
        $usuarioActual = Auth::user();

        if ($usuarioActual instanceof Usuario && $usuarioActual->esCliente()) {
            $consulta->whereKey($usuarioActual->cliente_id);
        }

        if (! empty($filtros['busqueda'])) {
            $busqueda = $filtros['busqueda'];
            $coincidencia = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $consulta->where(function ($consulta) use ($busqueda, $coincidencia) {
                $consulta->where('nombre', $coincidencia, "%{$busqueda}%")
                    ->orWhere('codigo', $coincidencia, "%{$busqueda}%")
                    ->orWhere('nit', $coincidencia, "%{$busqueda}%")
                    ->orWhere('numero_documento', $coincidencia, "%{$busqueda}%")
                    ->orWhere('correo', $coincidencia, "%{$busqueda}%");
            });
        }

        if (isset($filtros['estado']) && $filtros['estado'] !== '') {
            $consulta->where('estado', filter_var($filtros['estado'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filtros['tipo'])) {
            $consulta->where('tipo', $filtros['tipo']);
        }

        if (! empty($filtros['tipo_edificio'])) {
            $consulta->whereHas('edificios', fn ($e) => $e->where('tipo', $filtros['tipo_edificio']));
        }

        return $consulta;
    }

    public function listar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        return $this->consulta($filtros)->orderBy('nombre')->paginate($porPagina, ['*'], 'pagina');
    }

    public function crear(array $datos): Cliente
    {
        return DB::transaction(function () use ($datos) {
            $cliente = Cliente::create($datos);
            RegistroAuditoriaService::registrar('creado', $cliente, $cliente->toArray());

            return $cliente->load('contactos');
        });
    }

    public function actualizar(Cliente $cliente, array $datos): Cliente
    {
        return DB::transaction(function () use ($cliente, $datos) {
            $cliente->update($datos);
            RegistroAuditoriaService::registrar('actualizado', $cliente, $cliente->getChanges());

            return $cliente->fresh(['contactos']);
        });
    }

    /**
     * Un cliente con edificios registrados no se elimina; se desactiva para conservar su historial.
     */
    public function eliminar(Cliente $cliente): void
    {
        if ($cliente->edificios()->exists()) {
            throw new ReglaNegocioException('El cliente tiene edificios registrados. Desactívelo en lugar de eliminarlo.');
        }

        DB::transaction(function () use ($cliente) {
            $cliente->delete();
            RegistroAuditoriaService::registrar('eliminado', $cliente, []);
        });
    }

    public function agregarContacto(Cliente $cliente, array $datos): ContactoCliente
    {
        return DB::transaction(function () use ($cliente, $datos) {
            $contacto = $cliente->contactos()->create($datos);
            RegistroAuditoriaService::registrar('creado', $contacto, $contacto->toArray());

            return $contacto;
        });
    }
}
