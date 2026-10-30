<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\GuardarRepuestoRequest;
use App\Http\Requests\Inventario\MovimientoInventarioRequest;
use App\Http\Requests\Inventario\RepuestoOrdenRequest;
use App\Models\MovimientoInventario;
use App\Models\OrdenRepuesto;
use App\Models\OrdenTrabajo;
use App\Models\Repuesto;
use App\Models\Usuario;
use App\Services\InventarioService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** RF-040 a RF-043: repuestos, movimientos de inventario y repuestos usados en las órdenes. */
class InventarioController extends Controller
{
    public function __construct(private readonly InventarioService $inventario) {}

    private function exigir(Request $solicitud, string $permiso, string $accion): Usuario
    {
        $usuario = $solicitud->user();
        abort_unless($usuario->tienePermiso($permiso), 403, "Su rol no tiene permiso para {$accion}.");

        return $usuario;
    }

    private function metadatos(LengthAwarePaginator $pagina): array
    {
        return [
            'pagina_actual' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'por_pagina' => $pagina->perPage(),
            'total' => $pagina->total(),
            'desde' => $pagina->firstItem(),
            'hasta' => $pagina->lastItem(),
        ];
    }

    public function repuesto(Repuesto $r): array
    {
        return [
            'id' => $r->id,
            'codigo' => $r->codigo,
            'nombre' => $r->nombre,
            'marca' => $r->marca,
            'referencia' => $r->referencia,
            'unidad' => $r->unidad,
            'precio' => (float) $r->precio,
            'stock' => (float) $r->stock,
            'stock_minimo' => (float) $r->stock_minimo,
            'bajo_minimo' => $r->bajoMinimo(),
            'activo' => $r->activo,
            'observaciones' => $r->observaciones,
            'actualizado_en' => $r->updated_at?->toIso8601String(),
        ];
    }

    private function movimiento(MovimientoInventario $m): array
    {
        return [
            'id' => $m->id,
            'tipo' => $m->tipo,
            'cantidad' => (float) $m->cantidad,
            'variacion' => (float) $m->variacion,
            'stock_resultante' => (float) $m->stock_resultante,
            'motivo' => $m->motivo,
            'orden' => $m->orden ? ['id' => $m->orden->id, 'numero' => $m->orden->numero] : null,
            'usuario' => $m->usuario?->nombre,
            'fecha' => $m->created_at?->toIso8601String(),
        ];
    }

    private function usado(OrdenRepuesto $r): array
    {
        return [
            'id' => $r->id,
            'repuesto' => $r->repuesto ? ['id' => $r->repuesto->id, 'codigo' => $r->repuesto->codigo, 'nombre' => $r->repuesto->nombre, 'unidad' => $r->repuesto->unidad, 'stock' => (float) $r->repuesto->stock] : null,
            'cantidad' => (float) $r->cantidad,
            'cantidad_descontada' => (float) $r->cantidad_descontada,
            'faltante' => (float) $r->faltante,
        ];
    }

    // ---------- Repuestos ----------

    public function index(Request $solicitud): JsonResponse
    {
        $this->exigir($solicitud, 'inventario.ver', 'consultar el inventario');
        $pagina = $this->inventario->listar($solicitud->all(), $this->porPagina($solicitud));

        return response()->json([
            'datos' => collect($pagina->items())->map(fn ($r) => $this->repuesto($r))->values(),
            'metadatos' => $this->metadatos($pagina),
            'resumen' => $this->inventario->resumen(),
        ]);
    }

    public function show(Request $solicitud, Repuesto $repuesto): JsonResponse
    {
        $this->exigir($solicitud, 'inventario.ver', 'consultar el inventario');

        return response()->json(['datos' => $this->repuesto($repuesto)]);
    }

    public function store(GuardarRepuestoRequest $solicitud): JsonResponse
    {
        $this->exigir($solicitud, 'inventario.gestionar', 'registrar repuestos');
        $repuesto = $this->inventario->crear($solicitud->validated());

        return response()->json(['datos' => $this->repuesto($repuesto), 'mensaje' => "Repuesto {$repuesto->codigo} registrado."], 201);
    }

    public function update(GuardarRepuestoRequest $solicitud, Repuesto $repuesto): JsonResponse
    {
        $this->exigir($solicitud, 'inventario.gestionar', 'actualizar repuestos');
        $repuesto = $this->inventario->actualizar($repuesto, $solicitud->validated());

        return response()->json(['datos' => $this->repuesto($repuesto), 'mensaje' => 'Repuesto actualizado.']);
    }

    public function alternarEstado(Request $solicitud, Repuesto $repuesto): JsonResponse
    {
        $this->exigir($solicitud, 'inventario.gestionar', 'activar o desactivar repuestos');
        $repuesto = $this->inventario->alternarEstado($repuesto);

        return response()->json(['datos' => $this->repuesto($repuesto), 'mensaje' => $repuesto->activo ? 'Repuesto activado.' : 'Repuesto desactivado.']);
    }

    public function destroy(Request $solicitud, Repuesto $repuesto): JsonResponse
    {
        $this->exigir($solicitud, 'inventario.gestionar', 'eliminar repuestos');
        $this->inventario->eliminar($repuesto);

        return response()->json(['mensaje' => 'Repuesto eliminado.']);
    }

    // ---------- Movimientos ----------

    public function movimientos(Request $solicitud, Repuesto $repuesto): JsonResponse
    {
        $this->exigir($solicitud, 'inventario.ver', 'consultar el inventario');
        $pagina = $this->inventario->movimientos($repuesto, $this->porPagina($solicitud));

        return response()->json([
            'datos' => collect($pagina->items())->map(fn ($m) => $this->movimiento($m))->values(),
            'metadatos' => $this->metadatos($pagina),
        ]);
    }

    public function registrarMovimiento(MovimientoInventarioRequest $solicitud, Repuesto $repuesto): JsonResponse
    {
        $this->exigir($solicitud, 'inventario.movimientos', 'registrar movimientos de inventario');
        $movimiento = $this->inventario->registrarMovimiento($repuesto, $solicitud->validated());

        return response()->json([
            'datos' => $this->movimiento($movimiento->load(['usuario', 'orden'])),
            'repuesto' => $this->repuesto($repuesto->fresh()),
            'mensaje' => MovimientoInventario::TIPOS[$movimiento->tipo].' registrada. Stock actual: '.InventarioService::cantidad((float) $movimiento->stock_resultante).' '.$repuesto->unidad.'.',
        ], 201);
    }

    // ---------- Repuestos de una orden ----------

    public function repuestosOrden(Request $solicitud, OrdenTrabajo $orden): JsonResponse
    {
        $this->authorize('view', $orden);

        return response()->json(['datos' => $orden->repuestos()->with('repuesto')->get()->map(fn ($r) => $this->usado($r))->values()]);
    }

    public function agregarAOrden(RepuestoOrdenRequest $solicitud, OrdenTrabajo $orden): JsonResponse
    {
        $this->authorize('registrarRepuestos', $orden);
        $repuesto = Repuesto::findOrFail($solicitud->integer('repuesto_id'));
        $registro = $this->inventario->agregarAOrden($orden, $repuesto, (float) $solicitud->validated('cantidad'));

        return response()->json(['datos' => $this->usado($registro), 'mensaje' => "{$repuesto->nombre} agregado a la orden {$orden->numero}."], 201);
    }

    public function quitarDeOrden(Request $solicitud, OrdenTrabajo $orden, OrdenRepuesto $registro): JsonResponse
    {
        $this->authorize('registrarRepuestos', $orden);
        $this->inventario->quitarDeOrden($orden, $registro);

        return response()->json(['mensaje' => 'Repuesto quitado de la orden.']);
    }
}
