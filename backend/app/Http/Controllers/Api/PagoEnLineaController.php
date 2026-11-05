<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Models\PagoEnLinea;
use App\Services\PagoEnLineaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pago de facturas por PSE (Wompi). El cliente paga sus facturas; cartera puede generar el enlace.
 */
class PagoEnLineaController extends Controller
{
    public function __construct(private readonly PagoEnLineaService $pagos) {}

    /** ¿Está configurada la pasarela? El panel muestra u oculta el botón «Pagar con PSE». */
    public function configuracion(): JsonResponse
    {
        return response()->json(['datos' => ['habilitado' => PagoEnLineaService::habilitado(), 'pruebas' => PagoEnLineaService::enPruebas()]]);
    }

    public function iniciar(Request $solicitud, Factura $factura): JsonResponse
    {
        $usuario = $solicitud->user();
        abort_unless($usuario->tienePermiso('cartera.ver'), 403, 'Su rol no puede consultar la cartera.');
        abort_if($usuario->esCliente() && $factura->cliente_id !== $usuario->cliente_id, 403, 'Esta factura no pertenece a su empresa.');
        $datos = $solicitud->validate(['monto' => ['nullable', 'numeric', 'min:1']]);

        return response()->json(['datos' => $this->pagos->iniciar($factura, $usuario, isset($datos['monto']) ? (float) $datos['monto'] : null)], 201);
    }

    /** Wompi devuelve al cliente con el id de la transacción: se consulta su estado real en Wompi. */
    public function confirmar(Request $solicitud): JsonResponse
    {
        $datos = $solicitud->validate(['transaccion_id' => ['required', 'string', 'max:80']]);
        $intento = $this->pagos->confirmar($datos['transaccion_id']);
        $usuario = $solicitud->user();
        abort_if($usuario->esCliente() && $intento->factura->cliente_id !== $usuario->cliente_id, 403, 'Este pago no pertenece a su empresa.');

        return response()->json(['datos' => $this->resumen($intento)]);
    }

    /** Eventos de Wompi (sin sesión; se valida la firma con el secreto de eventos). */
    public function evento(Request $solicitud): JsonResponse
    {
        try {
            $this->pagos->evento($solicitud->all());
        } catch (ReglaNegocioException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 400);
        }

        return response()->json(['recibido' => true]);
    }

    private function resumen(PagoEnLinea $intento): array
    {
        return [
            'referencia' => $intento->referencia,
            'estado' => $intento->estado,
            'monto' => $intento->monto_centavos / 100,
            'medio' => $intento->medio,
            'factura' => ['id' => $intento->factura->id, 'numero' => $intento->factura->numero, 'saldo' => $intento->factura->saldo],
            'mensaje' => $intento->respuesta['status_message'] ?? null,
        ];
    }
}
