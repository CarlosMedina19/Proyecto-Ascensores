<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cartera mínima: facturas por cliente y pagos aplicados a una factura. El saldo de la factura baja
 * con cada pago y nunca queda negativo.
 */
class CarteraService
{
    /** Consulta con los filtros del listado (también la usa la exportación a Excel). */
    public function consultaFacturas(array $filtros = []): Builder
    {
        $consulta = Factura::with('cliente')->withCount('pagos');
        $usuario = Auth::user();
        if ($usuario instanceof Usuario && $usuario->esCliente()) {
            $consulta->where('cliente_id', $usuario->cliente_id);
        }
        if (! empty($filtros['cliente_id'])) {
            $consulta->where('cliente_id', $filtros['cliente_id']);
        }
        if (! empty($filtros['estado'])) {
            $filtros['estado'] === 'vencida'
                ? $consulta->where('saldo', '>', 0)->whereDate('fecha_vencimiento', '<', now())
                : $consulta->where('estado', $filtros['estado']);
        }
        if (! empty($filtros['busqueda'])) {
            $como = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $texto = '%'.$filtros['busqueda'].'%';
            $consulta->where(fn ($c) => $c->where('numero', $como, $texto)->orWhereHas('cliente', fn ($q) => $q->where('nombre', $como, $texto)));
        }

        return $consulta;
    }

    public function facturas(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        return $this->consultaFacturas($filtros)->orderByDesc('fecha_emision')->orderByDesc('id')->paginate($porPagina, ['*'], 'pagina');
    }

    public function crearFactura(array $datos): Factura
    {
        $cliente = Cliente::findOrFail($datos['cliente_id']);
        if (! $cliente->estado) {
            throw new ReglaNegocioException('El cliente está inactivo. Actívelo antes de facturarle.');
        }

        return DB::transaction(function () use ($datos) {
            $factura = Factura::create([
                ...$datos,
                'saldo' => $datos['valor'],
                'estado' => 'pendiente',
                'creado_por' => Auth::id(),
            ]);
            RegistroAuditoriaService::registrar('creado', $factura, $factura->toArray(), null, 'cartera');

            return $factura->load('cliente');
        });
    }

    /** Registra un pago y lo aplica a la factura; el valor no puede superar el saldo. */
    public function registrarPago(array $datos): Pago
    {
        return DB::transaction(function () use ($datos) {
            $factura = Factura::lockForUpdate()->findOrFail($datos['factura_id']);
            $valor = round((float) $datos['valor'], 2);
            if ($valor > (float) $factura->saldo) {
                throw new ReglaNegocioException(
                    'El pago de '.self::pesos($valor).' supera el saldo de la factura '.$factura->numero
                    .' ('.self::pesos((float) $factura->saldo).'). Registre un valor igual o menor.'
                );
            }
            if ($factura->pagos()->where('referencia', $datos['referencia'])->exists()) {
                throw new ReglaNegocioException('La referencia '.$datos['referencia'].' ya está registrada en la factura '.$factura->numero.'.');
            }

            $pago = Pago::create([
                'cliente_id' => $factura->cliente_id,
                'factura_id' => $factura->id,
                'valor' => $valor,
                'fecha' => $datos['fecha'],
                'medio' => $datos['medio'],
                'referencia' => $datos['referencia'],
                'registrado_por' => Auth::id(),
            ]);

            $saldo = round((float) $factura->saldo - $valor, 2);
            $factura->update(['saldo' => $saldo, 'estado' => $saldo <= 0 ? 'pagada' : 'parcial']);
            RegistroAuditoriaService::registrar('creado', $pago, $pago->toArray(), null, 'cartera');

            return $pago->load('factura');
        });
    }

    /** Saldo total, vencido y por antigüedad (corriente, 1–30, 31–60, 61–90 y más de 90 días). */
    public function resumen(?int $clienteId = null): array
    {
        $usuario = Auth::user();
        if ($usuario instanceof Usuario && $usuario->esCliente()) {
            $clienteId = $usuario->cliente_id;
        }
        $consulta = Factura::where('saldo', '>', 0)->when($clienteId, fn ($c) => $c->where('cliente_id', $clienteId));

        $tramos = ['corriente' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'mas_90' => 0.0];
        $clientesEnMora = [];
        foreach ($consulta->get() as $factura) {
            $dias = $factura->diasMora();
            $clave = match (true) {
                $dias <= 0 => 'corriente',
                $dias <= 30 => '1_30',
                $dias <= 60 => '31_60',
                $dias <= 90 => '61_90',
                default => 'mas_90',
            };
            $tramos[$clave] += (float) $factura->saldo;
            if ($dias > 0) {
                $clientesEnMora[$factura->cliente_id] = true;
            }
        }
        $total = array_sum($tramos);

        return [
            'total' => $total,
            'vencida' => $total - $tramos['corriente'],
            'tramos' => $tramos,
            'clientes_en_mora' => count($clientesEnMora),
            'recaudo_mes' => (float) Pago::whereYear('fecha', now()->year)->whereMonth('fecha', now()->month)
                ->when($clienteId, fn ($c) => $c->where('cliente_id', $clienteId))
                ->sum('valor'),
        ];
    }

    private static function pesos(float $valor): string
    {
        return '$ '.number_format($valor, 0, ',', '.');
    }
}
