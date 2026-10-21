<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\PlanMantenimiento;
use App\Models\Usuario;
use App\Services\RegistroAuditoriaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Acciones rápidas de las tablas: activar o desactivar un registro y eliminar usuarios.
 */
class EstadoRegistroController extends Controller
{
    private function exigir(Request $solicitud, string $permiso, string $accion): Usuario
    {
        $usuario = $solicitud->user();
        abort_unless($usuario->tienePermiso($permiso), 403, "Su rol no tiene permiso para {$accion}.");

        return $usuario;
    }

    /** Cambia el campo booleano y lo deja en la auditoría. */
    private function alternar(Model $registro, string $campo, string $modulo, string $nombre): JsonResponse
    {
        $nuevo = DB::transaction(function () use ($registro, $campo, $modulo) {
            $valor = ! $registro->{$campo};
            $registro->update([$campo => $valor]);
            RegistroAuditoriaService::registrar($valor ? 'activado' : 'desactivado', $registro, [$campo => $valor], null, $modulo);

            return $valor;
        });

        return response()->json([
            'datos' => ['id' => $registro->id, 'activo' => $nuevo],
            'mensaje' => $nombre.($nuevo ? ' activado.' : ' desactivado.'),
        ]);
    }

    public function cliente(Request $solicitud, Cliente $cliente): JsonResponse
    {
        $this->exigir($solicitud, 'clientes.editar', 'activar o desactivar clientes');

        return $this->alternar($cliente, 'estado', 'clientes', "Cliente {$cliente->nombre}");
    }

    public function edificio(Request $solicitud, Edificio $edificio): JsonResponse
    {
        $this->exigir($solicitud, 'edificios.editar', 'activar o desactivar edificios');

        return $this->alternar($edificio, 'activo', 'edificios', "Edificio {$edificio->nombre}");
    }

    public function plan(Request $solicitud, PlanMantenimiento $plan): JsonResponse
    {
        $this->exigir($solicitud, 'planes.editar', 'activar o desactivar planes');

        return $this->alternar($plan, 'activo', 'planes', "Plan {$plan->nombre}");
    }

    /** Ascensor: activo ↔ inactivo (uno en mantenimiento pasa a inactivo). */
    public function equipo(Request $solicitud, Equipo $equipo): JsonResponse
    {
        $this->exigir($solicitud, 'equipos.editar', 'activar o desactivar ascensores');
        $nuevo = $equipo->estado === 'inactivo' ? 'activo' : 'inactivo';

        DB::transaction(function () use ($equipo, $nuevo, $solicitud) {
            $anterior = $equipo->estado;
            $equipo->update(['estado' => $nuevo]);
            $equipo->historial()->create([
                'usuario_id' => $solicitud->user()->id,
                'evento' => 'cambio_estado',
                'descripcion' => "Estado cambiado de {$anterior} a {$nuevo} desde la tabla de ascensores.",
            ]);
            RegistroAuditoriaService::registrar($nuevo === 'activo' ? 'activado' : 'desactivado', $equipo, ['estado' => $nuevo], null, 'equipos');
        });

        return response()->json([
            'datos' => ['id' => $equipo->id, 'estado' => $nuevo],
            'mensaje' => "Ascensor {$equipo->codigo} ".($nuevo === 'activo' ? 'activado.' : 'desactivado.'),
        ]);
    }

    /**
     * Elimina un usuario (o técnico). Es lógica: deja de aparecer y no puede ingresar, pero las órdenes,
     * el historial y la auditoría siguen mostrando su nombre. El correo queda libre para una cuenta nueva.
     */
    public function eliminarUsuario(Request $solicitud, Usuario $usuario): JsonResponse
    {
        $actual = $this->exigir($solicitud, 'usuarios.editar', 'eliminar usuarios');
        if ($actual->id === $usuario->id) {
            throw new ReglaNegocioException('No puede eliminar su propia cuenta. Pídale a otro administrador que lo haga.');
        }
        if ($usuario->tieneRol('administrador') && Usuario::whereHas('rol', fn ($r) => $r->where('nombre', 'administrador'))->count() <= 1) {
            throw new ReglaNegocioException('Es el único administrador. Cree otro administrador antes de eliminar esta cuenta.');
        }

        DB::transaction(function () use ($usuario) {
            $usuario->tokens()->delete();
            $usuario->update([
                'activo' => false,
                'correo' => mb_substr("eliminado-{$usuario->id}-{$usuario->correo}", 0, 255),
            ]);
            $usuario->delete();
            RegistroAuditoriaService::registrar('eliminado', $usuario, ['nombre' => $usuario->nombre], null, 'usuarios');
        });

        return response()->json(['mensaje' => "Usuario {$usuario->nombre} eliminado. Su historial se conserva."]);
    }
}
