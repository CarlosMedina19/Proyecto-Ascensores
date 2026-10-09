<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ContactoCliente;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClienteService
{
    public function listar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $consulta = Cliente::with(['contactos'])->withCount('edificios');
        $usuarioActual = Auth::user();

        if ($usuarioActual instanceof Usuario && $usuarioActual->tieneRol('cliente')) {
            $consulta->whereKey($usuarioActual->cliente_id);
        }

        if (! empty($filtros['busqueda'])) {
            $busqueda = $filtros['busqueda'];
            $coincidencia = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $consulta->where(function ($consulta) use ($busqueda, $coincidencia) {
                $consulta->where('nombre', $coincidencia, "%{$busqueda}%")
                    ->orWhere('nit', $coincidencia, "%{$busqueda}%")
                    ->orWhere('numero_documento', $coincidencia, "%{$busqueda}%")
                    ->orWhere('correo', $coincidencia, "%{$busqueda}%");
            });
        }

        if (isset($filtros['estado'])) {
            $consulta->where('estado', filter_var($filtros['estado'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filtros['tipo'])) {
            $consulta->where('tipo', $filtros['tipo']);
        }

        return $consulta->latest()->paginate($porPagina);
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

    public function eliminar(Cliente $cliente): void
    {
        DB::transaction(function () use ($cliente) {
            $cliente->delete();
            RegistroAuditoriaService::registrar('eliminado', $cliente);
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
