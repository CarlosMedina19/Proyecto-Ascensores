<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\OrdenTrabajo;
use App\Models\Usuario;
use App\Services\OrdenTrabajoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Búsqueda global del encabezado (Ctrl+K): ascensores, edificios, clientes y contratos.
 * Respeta los permisos del rol y el cliente ve solo lo suyo.
 */
class BusquedaController extends Controller
{
    private const LIMITE = 5;

    public function __invoke(Request $solicitud): JsonResponse
    {
        $datos = $solicitud->validate(['q' => ['required', 'string', 'min:2', 'max:100']], [], ['q' => 'búsqueda']);
        /** @var Usuario $usuario */
        $usuario = $solicitud->user();
        $texto = '%'.addcslashes(trim($datos['q']), '%_\\').'%';
        $como = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $clienteId = $usuario->esCliente() ? $usuario->cliente_id : null;
        $resultados = [];

        if ($usuario->tienePermiso('equipos.ver')) {
            $resultados['ascensores'] = Equipo::with('edificio')
                ->when($clienteId, fn ($c) => $c->whereHas('edificio', fn ($e) => $e->where('cliente_id', $clienteId)))
                ->where(fn ($c) => $c->where('codigo', $como, $texto)
                    ->orWhere('marca', $como, $texto)
                    ->orWhere('numero_serie', $como, $texto)
                    ->orWhere('ubicacion', $como, $texto))
                ->orderBy('codigo')
                ->limit(self::LIMITE)
                ->get()
                ->map(fn (Equipo $e) => [
                    'id' => $e->id,
                    'titulo' => $e->codigo,
                    'detalle' => collect([$e->edificio?->nombre, $e->ubicacion])->filter()->implode(' · '),
                    'enlace' => "/equipos/{$e->id}",
                ]);
        }

        // Fase 3: órdenes de trabajo por número (el técnico solo encuentra las suyas).
        if ($usuario->tienePermiso('ordenes.ver')) {
            $resultados['ordenes'] = app(OrdenTrabajoService::class)->consultaVisible($usuario)
                ->with(['equipo', 'edificio'])
                ->where(fn ($c) => $c->where('numero', $como, $texto)
                    ->orWhereHas('equipo', fn ($e) => $e->where('codigo', $como, $texto)))
                ->orderByDesc('fecha')
                ->limit(self::LIMITE)
                ->get()
                ->map(fn (OrdenTrabajo $o) => [
                    'id' => $o->id,
                    'titulo' => $o->numero,
                    'detalle' => collect([$o->equipo?->codigo, $o->edificio?->nombre, $o->fecha->format('Y-m-d')])->filter()->implode(' · '),
                    'enlace' => "/ordenes/{$o->id}",
                ]);
        }

        if ($usuario->tienePermiso('edificios.ver')) {
            $resultados['edificios'] = Edificio::with('cliente')
                ->when($clienteId, fn ($c) => $c->where('cliente_id', $clienteId))
                ->where(fn ($c) => $c->where('nombre', $como, $texto)->orWhere('direccion', $como, $texto))
                ->orderBy('nombre')
                ->limit(self::LIMITE)
                ->get()
                ->map(fn (Edificio $e) => [
                    'id' => $e->id,
                    'titulo' => $e->nombre,
                    'detalle' => collect([$e->cliente?->nombre, $e->ciudad])->filter()->implode(' · '),
                    'enlace' => "/edificios/{$e->id}",
                ]);
        }

        if ($usuario->tienePermiso('clientes.ver')) {
            $resultados['clientes'] = Cliente::query()
                ->when($clienteId, fn ($c) => $c->whereKey($clienteId))
                ->where(fn ($c) => $c->where('nombre', $como, $texto)
                    ->orWhere('nit', $como, $texto)
                    ->orWhere('numero_documento', $como, $texto))
                ->orderBy('nombre')
                ->limit(self::LIMITE)
                ->get()
                ->map(fn (Cliente $c) => [
                    'id' => $c->id,
                    'titulo' => $c->nombre,
                    'detalle' => $c->nit ?: $c->numero_documento,
                    'enlace' => "/clientes/{$c->id}",
                ]);
        }

        if ($usuario->tienePermiso('contratos.ver')) {
            $resultados['contratos'] = Contrato::with('cliente')
                ->when($clienteId, fn ($c) => $c->where('cliente_id', $clienteId))
                ->where(fn ($c) => $c->where('numero', $como, $texto)
                    ->orWhereHas('cliente', fn ($cl) => $cl->where('nombre', $como, $texto)))
                ->orderByDesc('fecha_fin')
                ->limit(self::LIMITE)
                ->get()
                ->map(fn (Contrato $c) => [
                    'id' => $c->id,
                    'titulo' => $c->numero,
                    'detalle' => "{$c->cliente?->nombre} · {$c->estado}",
                    'enlace' => "/contratos/{$c->id}",
                ]);
        }

        return response()->json(['datos' => $resultados]);
    }
}
