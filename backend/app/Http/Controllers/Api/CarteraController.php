<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cartera\GuardarFacturaRequest;
use App\Http\Requests\Cartera\RegistrarPagoRequest;
use App\Models\Factura;
use App\Models\Usuario;
use App\Services\CarteraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Cartera mínima: facturas, pagos y saldo por antigüedad. */
class CarteraController extends Controller
{
    public function __construct(private readonly CarteraService $cartera) {}

    private function exigir(Request $solicitud, string $permiso, string $accion): Usuario
    {
        $usuario = $solicitud->user();
        abort_unless($usuario->tienePermiso($permiso), 403, "Su rol no tiene permiso para {$accion}.");

        return $usuario;
    }

    public function resumen(Request $solicitud): JsonResponse
    {
        $this->exigir($solicitud, 'cartera.ver', 'consultar la cartera');

        return response()->json(['datos' => $this->cartera->resumen($solicitud->integer('cliente_id') ?: null)]);
    }

    public function facturas(Request $solicitud): JsonResponse
    {
        $this->exigir($solicitud, 'cartera.ver', 'consultar la cartera');
        $pagina = $this->cartera->facturas($solicitud->all(), $this->porPagina($solicitud));

        return response()->json([
            'datos' => collect($pagina->items())->map(fn (Factura $f) => $this->factura($f))->values(),
            'metadatos' => [
                'pagina_actual' => $pagina->currentPage(),
                'ultima_pagina' => $pagina->lastPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
                'desde' => $pagina->firstItem(),
                'hasta' => $pagina->lastItem(),
            ],
        ]);
    }

    public function factura(Factura $factura, bool $conPagos = false): array
    {
        return [
            'id' => $factura->id,
            'numero' => $factura->numero,
            'concepto' => $factura->concepto,
            'cliente' => $factura->cliente ? ['id' => $factura->cliente->id, 'codigo' => $factura->cliente->codigo, 'nombre' => $factura->cliente->nombre] : null,
            'fecha_emision' => $factura->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $factura->fecha_vencimiento?->toDateString(),
            'valor' => (float) $factura->valor,
            'saldo' => (float) $factura->saldo,
            'estado' => $factura->estado,
            'dias_mora' => $factura->diasMora(),
            'pagos' => $conPagos ? $factura->pagos->map(fn ($p) => [
                'id' => $p->id,
                'valor' => (float) $p->valor,
                'fecha' => $p->fecha->toDateString(),
                'medio' => $p->medio,
                'referencia' => $p->referencia,
            ])->values() : null,
        ];
    }

    public function verFactura(Request $solicitud, Factura $factura): JsonResponse
    {
        $usuario = $this->exigir($solicitud, 'cartera.ver', 'consultar la cartera');
        abort_if($usuario->esCliente() && $factura->cliente_id !== $usuario->cliente_id, 403, 'Esta factura no pertenece a su empresa.');

        return response()->json(['datos' => $this->factura($factura->load(['cliente', 'pagos']), true)]);
    }

    public function crearFactura(GuardarFacturaRequest $solicitud): JsonResponse
    {
        $this->exigir($solicitud, 'facturas.crear', 'registrar facturas');
        $factura = $this->cartera->crearFactura($solicitud->validated());

        return response()->json(['datos' => $this->factura($factura), 'mensaje' => "Factura {$factura->numero} registrada."], 201);
    }

    public function registrarPago(RegistrarPagoRequest $solicitud): JsonResponse
    {
        $this->exigir($solicitud, 'pagos.registrar', 'registrar pagos');
        $pago = $this->cartera->registrarPago($solicitud->validated());

        return response()->json([
            'datos' => $this->factura($pago->factura->fresh(['cliente', 'pagos']), true),
            'mensaje' => 'Pago de $ '.number_format((float) $pago->valor, 0, ',', '.').' registrado en la factura '.$pago->factura->numero.'.',
        ], 201);
    }
}
