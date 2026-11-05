<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Factura;
use App\Models\PagoEnLinea;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pago de facturas por PSE con la pasarela Wompi (Web Checkout).
 *
 * 1. iniciar(): crea el intento con una referencia única y arma la URL del checkout firmada con el
 *    secreto de integridad: SHA-256(referencia + monto en centavos + moneda + secreto).
 * 2. Wompi devuelve al cliente a /pagos/resultado?id=<transacción>; confirmar() consulta la transacción
 *    en la API de Wompi (no se confía en lo que diga el navegador).
 * 3. El evento transaction.updated que envía Wompi (con su firma) también confirma el pago.
 * Un pago aprobado se registra una sola vez en la factura, con el medio "pse".
 */
class PagoEnLineaService
{
    public function __construct(private readonly CarteraService $cartera) {}

    public static function habilitado(): bool
    {
        return filled(config('services.wompi.llave_publica')) && filled(config('services.wompi.secreto_integridad'));
    }

    /** Modo de pruebas: llaves pub_test_… (no se cobra dinero real). */
    public static function enPruebas(): bool
    {
        return str_starts_with((string) config('services.wompi.llave_publica'), 'pub_test_');
    }

    /** @return array{url: string, referencia: string, monto: float} */
    public function iniciar(Factura $factura, Usuario $usuario, ?float $monto = null): array
    {
        if (! self::habilitado()) {
            throw new ReglaNegocioException('El pago en línea no está configurado. Registre las llaves de Wompi en el servidor.');
        }
        $saldo = (float) $factura->saldo;
        if ($saldo <= 0) {
            throw new ReglaNegocioException("La factura {$factura->numero} ya está pagada.");
        }
        $monto = round($monto ?? $saldo);
        if ($monto <= 0 || $monto > $saldo) {
            throw new ReglaNegocioException('El valor a pagar debe ser mayor que cero y no superar el saldo de la factura.');
        }

        $intento = PagoEnLinea::create([
            'factura_id' => $factura->id,
            'referencia' => 'SIGMA-'.$factura->numero.'-'.Str::upper(Str::random(8)),
            'monto_centavos' => (int) ($monto * 100),
            'usuario_id' => $usuario->id,
        ]);
        RegistroAuditoriaService::registrar('creado', $intento, ['factura' => $factura->numero, 'monto' => $monto], null, 'cartera');

        $parametros = [
            'public-key' => config('services.wompi.llave_publica'),
            'currency' => 'COP',
            'amount-in-cents' => $intento->monto_centavos,
            'reference' => $intento->referencia,
            'signature:integrity' => self::firma($intento->referencia, $intento->monto_centavos),
            'redirect-url' => config('services.wompi.retorno'),
        ];

        return [
            'url' => config('services.wompi.checkout').'?'.http_build_query($parametros),
            'referencia' => $intento->referencia,
            'monto' => $monto,
        ];
    }

    public static function firma(string $referencia, int $centavos): string
    {
        return hash('sha256', $referencia.$centavos.'COP'.config('services.wompi.secreto_integridad'));
    }

    /** Consulta la transacción en Wompi y aplica su estado. */
    public function confirmar(string $transaccionId): PagoEnLinea
    {
        $respuesta = Http::timeout(15)->acceptJson()->get(rtrim(config('services.wompi.api'), '/').'/transactions/'.rawurlencode($transaccionId));
        if (! $respuesta->successful() || ! is_array($respuesta->json('data'))) {
            throw new ReglaNegocioException('No fue posible consultar el pago en Wompi. Intente de nuevo en unos minutos.');
        }

        return $this->aplicar($respuesta->json('data'));
    }

    /**
     * Evento de Wompi (transaction.updated). La firma es SHA-256 de los valores indicados en
     * signature.properties, el timestamp y el secreto de eventos.
     */
    public function evento(array $evento): ?PagoEnLinea
    {
        $secreto = (string) config('services.wompi.secreto_eventos');
        $propiedades = data_get($evento, 'signature.properties', []);
        $cadena = implode('', array_map(fn ($ruta) => (string) data_get($evento, "data.{$ruta}"), $propiedades)).data_get($evento, 'timestamp').$secreto;
        if ($secreto === '' || ! hash_equals(hash('sha256', $cadena), (string) data_get($evento, 'signature.checksum'))) {
            throw new ReglaNegocioException('La firma del evento no es válida.');
        }
        if (data_get($evento, 'event') !== 'transaction.updated' || ! is_array(data_get($evento, 'data.transaction'))) {
            return null;
        }

        return $this->aplicar(data_get($evento, 'data.transaction'));
    }

    /** Aplica el estado de la transacción al intento; si quedó aprobada, registra el pago (una sola vez). */
    private function aplicar(array $transaccion): PagoEnLinea
    {
        return DB::transaction(function () use ($transaccion) {
            $intento = PagoEnLinea::where('referencia', (string) ($transaccion['reference'] ?? ''))->lockForUpdate()->first();
            if (! $intento) {
                throw new ReglaNegocioException('El pago no corresponde a ninguna factura de SIGMA.');
            }
            if ((int) ($transaccion['amount_in_cents'] ?? 0) !== $intento->monto_centavos || ($transaccion['currency'] ?? 'COP') !== 'COP') {
                Log::warning('Wompi: el monto no coincide con el intento', ['referencia' => $intento->referencia]);
                throw new ReglaNegocioException('El valor pagado no coincide con el de la factura.');
            }

            $estado = ['APPROVED' => 'aprobado', 'DECLINED' => 'rechazado', 'VOIDED' => 'anulado', 'ERROR' => 'error'][$transaccion['status'] ?? ''] ?? 'pendiente';
            $intento->fill([
                'estado' => $intento->pago_id ? 'aprobado' : $estado,
                'transaccion_id' => $transaccion['id'] ?? $intento->transaccion_id,
                'medio' => $transaccion['payment_method_type'] ?? $intento->medio,
                'respuesta' => array_intersect_key($transaccion, array_flip(['id', 'status', 'status_message', 'payment_method_type', 'created_at', 'finalized_at'])),
            ])->save();

            if ($estado === 'aprobado' && ! $intento->pago_id) {
                $pago = $this->cartera->registrarPago([
                    'factura_id' => $intento->factura_id,
                    'valor' => $intento->monto_centavos / 100,
                    'fecha' => now()->toDateString(),
                    'medio' => 'pse',
                    'referencia' => 'WOMPI-'.($transaccion['id'] ?? $intento->referencia),
                ]);
                $intento->update(['pago_id' => $pago->id]);
            }

            return $intento->fresh(['factura', 'pago']);
        });
    }
}
