<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\MovimientoInventario;
use App\Models\OrdenRepuesto;
use App\Models\OrdenTrabajo;
use App\Models\Repuesto;
use App\Models\Usuario;
use App\Notifications\InventarioNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * RF-040 a RF-043: repuestos, movimientos de inventario, consumo automático al finalizar una orden y
 * alertas de stock mínimo. El stock nunca queda negativo y cada cambio deja un movimiento.
 */
class InventarioService
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    /** Consulta con los filtros del listado (también la usa la exportación a Excel). */
    public function consulta(array $filtros = []): Builder
    {
        $como = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return Repuesto::query()
            ->when(($filtros['estado'] ?? null) === 'activos', fn ($q) => $q->where('activo', true))
            ->when(($filtros['estado'] ?? null) === 'inactivos', fn ($q) => $q->where('activo', false))
            ->when(! empty($filtros['bajo_minimo']), fn ($q) => $q->where('stock_minimo', '>', 0)->whereColumn('stock', '<=', 'stock_minimo'))
            ->when($filtros['busqueda'] ?? null, fn ($q, $texto) => $q->where(fn ($b) => $b
                ->where('codigo', $como, "%{$texto}%")->orWhere('nombre', $como, "%{$texto}%")
                ->orWhere('marca', $como, "%{$texto}%")->orWhere('referencia', $como, "%{$texto}%")));
    }

    public function listar(array $filtros, int $porPagina): LengthAwarePaginator
    {
        return $this->consulta($filtros)->orderByDesc('activo')->orderBy('nombre')->paginate($porPagina, ['*'], 'pagina');
    }

    /** Totales del inventario: repuestos activos, bajo mínimo y valor del stock. */
    public function resumen(): array
    {
        $activos = Repuesto::where('activo', true);

        return [
            'repuestos' => (clone $activos)->count(),
            'bajo_minimo' => (clone $activos)->where('stock_minimo', '>', 0)->whereColumn('stock', '<=', 'stock_minimo')->count(),
            'sin_stock' => (clone $activos)->where('stock', '<=', 0)->count(),
            'valor' => round((float) (clone $activos)->sum(DB::raw('stock * precio')), 2),
        ];
    }

    /** RF-040: el stock inicial, si lo hay, entra como un movimiento de entrada. */
    public function crear(array $datos): Repuesto
    {
        return DB::transaction(function () use ($datos) {
            $inicial = round((float) ($datos['stock'] ?? 0), 2);
            $repuesto = Repuesto::create([...$datos, 'stock' => 0, 'activo' => $datos['activo'] ?? true]);
            if ($inicial > 0) {
                $this->mover($repuesto, 'entrada', $inicial, 'Inventario inicial.');
            }
            RegistroAuditoriaService::registrar('creado', $repuesto, $repuesto->toArray(), null, 'inventario');

            return $repuesto->fresh();
        });
    }

    /** El stock no se edita aquí: solo cambia con movimientos (RF-041). */
    public function actualizar(Repuesto $repuesto, array $datos): Repuesto
    {
        unset($datos['stock']);
        $antes = $repuesto->only(array_keys($datos));
        $repuesto->update($datos);
        RegistroAuditoriaService::registrar('actualizado', $repuesto, ['antes' => $antes, 'despues' => $repuesto->only(array_keys($datos))], null, 'inventario');
        $this->revisarStockMinimo($repuesto->fresh());

        return $repuesto->fresh();
    }

    public function alternarEstado(Repuesto $repuesto): Repuesto
    {
        $repuesto->update(['activo' => ! $repuesto->activo]);
        RegistroAuditoriaService::registrar($repuesto->activo ? 'activado' : 'desactivado', $repuesto, ['activo' => $repuesto->activo], null, 'inventario');

        return $repuesto;
    }

    /** RF-040: un repuesto con movimientos o usado en órdenes no se elimina; se desactiva. */
    public function eliminar(Repuesto $repuesto): void
    {
        if ($repuesto->movimientos()->exists() || OrdenRepuesto::where('repuesto_id', $repuesto->id)->exists()) {
            throw new ReglaNegocioException('El repuesto tiene movimientos registrados. Desactívelo en lugar de eliminarlo.');
        }
        RegistroAuditoriaService::registrar('eliminado', $repuesto, $repuesto->toArray(), null, 'inventario');
        $repuesto->delete();
    }

    /**
     * RF-041: entrada, salida, devolución o ajuste. El ajuste lleva el stock al valor contado
     * (sube o baja) y es la forma de corregir errores.
     */
    public function registrarMovimiento(Repuesto $repuesto, array $datos): MovimientoInventario
    {
        return DB::transaction(function () use ($repuesto, $datos) {
            $repuesto = Repuesto::lockForUpdate()->findOrFail($repuesto->id);
            if (! $repuesto->activo && $datos['tipo'] !== 'ajuste') {
                throw new ReglaNegocioException('El repuesto está inactivo. Actívelo para registrar movimientos.');
            }

            if ($datos['tipo'] === 'ajuste') {
                $contado = round((float) $datos['stock_contado'], 2);
                $diferencia = round($contado - (float) $repuesto->stock, 2);
                if ($diferencia == 0) {
                    throw new ReglaNegocioException('El stock contado es igual al registrado; no hay nada que ajustar.');
                }

                return $this->mover($repuesto, 'ajuste', $diferencia, $datos['motivo']);
            }

            $cantidad = round((float) $datos['cantidad'], 2);

            return $this->mover($repuesto, $datos['tipo'], $datos['tipo'] === 'salida' ? -$cantidad : $cantidad, $datos['motivo']);
        });
    }

    public function movimientos(Repuesto $repuesto, int $porPagina): LengthAwarePaginator
    {
        return $repuesto->movimientos()->with(['usuario', 'orden'])->orderByDesc('created_at')->orderByDesc('id')->paginate($porPagina, ['*'], 'pagina');
    }

    // ------------------------------------------------------------------ repuestos de una orden

    /** RF-042: repuestos usados en la orden; se registran mientras la orden no esté finalizada ni cancelada. */
    public function agregarAOrden(OrdenTrabajo $orden, Repuesto $repuesto, float $cantidad): OrdenRepuesto
    {
        $this->exigirOrdenAbierta($orden);
        if (! $repuesto->activo) {
            throw new ReglaNegocioException("El repuesto {$repuesto->codigo} está inactivo.");
        }

        return DB::transaction(function () use ($orden, $repuesto, $cantidad) {
            $registro = OrdenRepuesto::lockForUpdate()->firstOrNew(['orden_id' => $orden->id, 'repuesto_id' => $repuesto->id]);
            $registro->cantidad = round((float) ($registro->cantidad ?? 0) + $cantidad, 2);
            $registro->registrado_por ??= Auth::id();
            $registro->save();

            return $registro->load('repuesto');
        });
    }

    public function quitarDeOrden(OrdenTrabajo $orden, OrdenRepuesto $registro): void
    {
        $this->exigirOrdenAbierta($orden);
        if ($registro->orden_id !== $orden->id) {
            throw new ReglaNegocioException('El repuesto no pertenece a esta orden.');
        }
        $registro->delete();
    }

    /**
     * RF-042: al finalizar la orden se descuentan sus repuestos. Si el stock no alcanza, se descuenta lo
     * disponible, el stock queda en cero (nunca negativo), el faltante queda registrado y se avisa al
     * coordinador. Debe llamarse dentro de la transacción del cierre de la orden.
     *
     * @return Collection<int, OrdenRepuesto>
     */
    public function consumirOrden(OrdenTrabajo $orden): Collection
    {
        $usados = $orden->repuestos()->with('repuesto')->get();
        $faltantes = collect();

        foreach ($usados as $registro) {
            $pendiente = round((float) $registro->cantidad - (float) $registro->cantidad_descontada, 2);
            if ($pendiente <= 0) {
                continue;
            }
            $repuesto = Repuesto::lockForUpdate()->findOrFail($registro->repuesto_id);
            $descontar = min($pendiente, (float) $repuesto->stock);
            if ($descontar > 0) {
                $this->mover($repuesto, 'consumo', -$descontar, "Usado en la orden {$orden->numero}.", $orden->id);
            }
            $faltante = round($pendiente - $descontar, 2);
            $registro->update([
                'cantidad_descontada' => round((float) $registro->cantidad_descontada + $descontar, 2),
                'faltante' => round((float) $registro->faltante + $faltante, 2),
            ]);
            if ($faltante > 0) {
                $faltantes->push($registro->fresh('repuesto'));
            }
        }

        if ($usados->isNotEmpty()) {
            $orden->equipo?->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'repuestos',
                'descripcion' => "Repuestos usados en la orden {$orden->numero}: "
                    .$usados->map(fn ($r) => self::cantidad((float) $r->cantidad)." {$r->repuesto->unidad} de {$r->repuesto->nombre}")->implode('; ').'.',
            ]);
        }
        if ($faltantes->isNotEmpty()) {
            $this->notificaciones->enviar(
                $this->destinatarios(),
                new InventarioNotification('faltante', $faltantes->first()->repuesto, $orden, $faltantes),
                'inventario_faltante_'.now()->format('YmdHisv'),
                'orden',
                $orden->id,
            );
        }

        return $usados;
    }

    // ------------------------------------------------------------------ apoyo

    /** Aplica el cambio de stock y deja el movimiento. Nunca permite stock negativo (RF-041). */
    private function mover(Repuesto $repuesto, string $tipo, float $variacion, string $motivo, ?int $ordenId = null): MovimientoInventario
    {
        $resultante = round((float) $repuesto->stock + $variacion, 2);
        if ($resultante < 0) {
            throw new ReglaNegocioException(sprintf(
                'La salida de %s %s supera el stock disponible de %s (%s).',
                self::cantidad(abs($variacion)), $repuesto->unidad, $repuesto->nombre, self::cantidad((float) $repuesto->stock),
            ));
        }

        $repuesto->update(['stock' => $resultante]);
        $movimiento = MovimientoInventario::create([
            'repuesto_id' => $repuesto->id,
            'tipo' => $tipo,
            'cantidad' => abs($variacion),
            'variacion' => $variacion,
            'stock_resultante' => $resultante,
            'motivo' => $motivo,
            'orden_id' => $ordenId,
            'usuario_id' => Auth::id(),
        ]);
        $this->revisarStockMinimo($repuesto);

        return $movimiento;
    }

    /** RF-043: alerta al llegar al mínimo, una sola vez hasta que el stock se reponga. */
    private function revisarStockMinimo(Repuesto $repuesto): void
    {
        if ($repuesto->bajoMinimo() && ! $repuesto->alerta_stock_en && $repuesto->activo) {
            $repuesto->update(['alerta_stock_en' => now()]);
            $this->notificaciones->enviar(
                $this->destinatarios(),
                new InventarioNotification('stock_minimo', $repuesto),
                // Una clave por baja al mínimo (el último movimiento), para volver a avisar tras reponer.
                'stock_minimo_'.$repuesto->movimientos()->max('id'),
                'repuesto',
                $repuesto->id,
            );
        } elseif (! $repuesto->bajoMinimo() && $repuesto->alerta_stock_en) {
            $repuesto->update(['alerta_stock_en' => null]);
        }
    }

    /** RF-043: las alertas de inventario llegan al coordinador y al administrador. */
    private function destinatarios(): Collection
    {
        return Usuario::query()
            ->where('activo', true)
            ->whereHas('rol', fn ($rol) => $rol->whereIn('nombre', ['coordinador', 'administrador']))
            ->get();
    }

    private function exigirOrdenAbierta(OrdenTrabajo $orden): void
    {
        if ($orden->esFinal()) {
            throw new ReglaNegocioException('La orden ya está '.($orden->estado === 'cancelada' ? 'cancelada' : 'finalizada').'; sus repuestos no se pueden cambiar.');
        }
    }

    public static function cantidad(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',');
    }
}
