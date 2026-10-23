<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Configuracion;
use App\Models\Cotizacion;
use App\Models\Equipo;
use App\Models\OrdenTrabajo;
use App\Models\PlanillaResultado;
use App\Models\Usuario;
use App\Notifications\CotizacionNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cotizaciones de mantenimientos correctivos: se envían al cliente y él las aprueba o rechaza desde su portal.
 */
class CotizacionService
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    public function listar(array $filtros, int $porPagina, ?Usuario $usuario): LengthAwarePaginator
    {
        return Cotizacion::query()
            ->with(['equipo.edificio', 'cliente'])
            ->when($usuario?->esCliente(), fn ($q) => $q->where('cliente_id', $usuario->cliente_id))
            ->when($filtros['estado'] ?? null, function ($q, $estado) {
                $hoy = now()->toDateString();
                match ($estado) {
                    'enviada' => $q->where('estado', 'enviada')->whereDate('vigente_hasta', '>=', $hoy),
                    'vencida' => $q->where(fn ($v) => $v->where('estado', 'vencida')
                        ->orWhere(fn ($e) => $e->where('estado', 'enviada')->whereDate('vigente_hasta', '<', $hoy))),
                    default => $q->where('estado', $estado),
                };
            })
            ->when($filtros['equipo_id'] ?? null, fn ($q, $id) => $q->where('equipo_id', $id))
            ->when($filtros['cliente_id'] ?? null, fn ($q, $id) => $q->where('cliente_id', $id))
            ->when($filtros['busqueda'] ?? null, fn ($q, $texto) => $q->where(fn ($b) => $b
                ->where('numero', 'ilike', "%{$texto}%")->orWhere('titulo', 'ilike', "%{$texto}%")
                ->orWhereHas('equipo', fn ($e) => $e->where('codigo', 'ilike', "%{$texto}%"))))
            ->orderByRaw("CASE WHEN estado = 'enviada' THEN 0 ELSE 1 END")
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate($porPagina, ['*'], 'pagina');
    }

    public function crear(array $datos): Cotizacion
    {
        $equipo = Equipo::with('edificio.cliente')->findOrFail($datos['equipo_id']);
        $cliente = $equipo->edificio?->cliente;
        if (! $cliente) {
            throw new ReglaNegocioException('El ascensor no tiene cliente asociado. Revise su edificio.');
        }
        if (! empty($datos['resultado_origen_id'])) {
            $resultado = PlanillaResultado::with('planilla.orden')->findOrFail($datos['resultado_origen_id']);
            if ($resultado->planilla?->orden?->equipo_id !== $equipo->id) {
                throw new ReglaNegocioException('El hallazgo no corresponde a este ascensor.');
            }
            if (! in_array($resultado->estado, ['R', 'M'], true)) {
                throw new ReglaNegocioException('Solo se cotizan los ítems en estado Regular o Malo.');
            }
        }

        $cotizacion = DB::transaction(function () use ($datos, $equipo, $cliente) {
            $items = collect($datos['items'])->map(fn (array $item) => [
                'descripcion' => $item['descripcion'],
                'cantidad' => round((float) $item['cantidad'], 2),
                'valor_unitario' => round((float) $item['valor_unitario'], 2),
                'valor_total' => round((float) $item['cantidad'] * (float) $item['valor_unitario'], 2),
            ]);
            $subtotal = round($items->sum('valor_total'), 2);
            $porcentajeIva = array_key_exists('iva_porcentaje', $datos) ? (float) $datos['iva_porcentaje'] : (float) Configuracion::valor('iva.porcentaje');
            $iva = round($subtotal * $porcentajeIva / 100, 2);

            $cotizacion = Cotizacion::create([
                'numero' => $this->siguienteNumero(),
                'equipo_id' => $equipo->id,
                'cliente_id' => $cliente->id,
                'resultado_origen_id' => $datos['resultado_origen_id'] ?? null,
                'titulo' => $datos['titulo'],
                'descripcion' => $datos['descripcion'] ?? null,
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $subtotal + $iva,
                'fecha_emision' => $datos['fecha_emision'] ?? now()->toDateString(),
                'vigente_hasta' => $datos['vigente_hasta'] ?? now()->addDays(30)->toDateString(),
                'estado' => 'enviada',
                'creada_por' => Auth::id(),
            ]);
            $cotizacion->items()->createMany($items->all());
            $equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'cotizacion_enviada',
                'descripcion' => "Cotización {$cotizacion->numero}: {$cotizacion->titulo}.",
            ]);
            RegistroAuditoriaService::registrar('creado', $cotizacion, $cotizacion->toArray(), null, 'cotizaciones');

            return $cotizacion;
        });

        $cotizacion->load(['equipo', 'cliente']);
        $this->notificaciones->enviar(
            Usuario::where('activo', true)->where('cliente_id', $cliente->id)->whereHas('rol', fn ($r) => $r->where('nombre', 'cliente'))->get(),
            new CotizacionNotification($cotizacion, 'enviada'),
            'cotizacion_enviada',
            'cotizacion',
            $cotizacion->id,
        );

        return $cotizacion;
    }

    /** El cliente aprueba o rechaza (con motivo). Solo una vez y dentro de la vigencia. */
    public function responder(Cotizacion $cotizacion, string $decision, ?string $motivo): Cotizacion
    {
        if ($cotizacion->estadoActual() !== 'enviada') {
            throw new ReglaNegocioException(match ($cotizacion->estadoActual()) {
                'vencida' => 'La cotización venció el '.$cotizacion->vigente_hasta->format('d/m/Y').'. Pida una nueva a su coordinador.',
                default => 'Esta cotización ya fue '.mb_strtolower(Cotizacion::ESTADOS[$cotizacion->estado]).'.',
            });
        }
        if ($decision === 'rechazada' && blank($motivo)) {
            throw new ReglaNegocioException('Escriba el motivo del rechazo para que podamos ajustar la propuesta.');
        }

        DB::transaction(function () use ($cotizacion, $decision, $motivo) {
            $cotizacion->update([
                'estado' => $decision,
                'respondida_en' => now(),
                'respondida_por' => Auth::id(),
                'motivo_rechazo' => $decision === 'rechazada' ? $motivo : null,
            ]);
            $cotizacion->equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => "cotizacion_{$decision}",
                'descripcion' => "Cotización {$cotizacion->numero} ".($decision === 'aprobada' ? 'aprobada por el cliente.' : "rechazada por el cliente: {$motivo}"),
            ]);
            RegistroAuditoriaService::registrar("cotizacion_{$decision}", $cotizacion, ['estado' => $decision, 'motivo' => $motivo], null, 'cotizaciones');
        });

        $cotizacion->load(['equipo', 'cliente']);
        $this->notificaciones->enviar(
            $this->notificaciones->coordinadores()->merge($this->notificaciones->destinatariosContratos())->unique('id'),
            new CotizacionNotification($cotizacion, $decision),
            "cotizacion_{$decision}",
            'cotizacion',
            $cotizacion->id,
        );

        return $cotizacion;
    }

    /**
     * Orden correctiva que ejecuta una cotización aprobada. Una cotización tiene una sola orden; por
     * defecto queda para el siguiente día hábil a las 08:00 y el coordinador puede reprogramarla.
     */
    public function crearOrden(Cotizacion $cotizacion, array $datos, OrdenTrabajoService $ordenes, CalendarioHabilService $calendario): OrdenTrabajo
    {
        if ($cotizacion->estadoActual() !== 'aprobada') {
            throw new ReglaNegocioException('Solo una cotización aprobada por el cliente genera la orden de trabajo.');
        }
        if ($cotizacion->orden_id) {
            throw new ReglaNegocioException("La cotización ya tiene la orden {$cotizacion->orden?->numero}.");
        }

        $cotizacion->loadMissing('items');
        $renglones = $cotizacion->items->map(fn ($i) => '· '.rtrim(rtrim(number_format((float) $i->cantidad, 2, ',', '.'), '0'), ',')." × {$i->descripcion}")->implode("
");

        return DB::transaction(function () use ($cotizacion, $datos, $ordenes, $calendario, $renglones) {
            $orden = $ordenes->crear([
                'servicio' => 'correctivo',
                'equipo_id' => $cotizacion->equipo_id,
                'fecha' => $datos['fecha'] ?? $calendario->siguienteHabil(CarbonImmutable::tomorrow())->toDateString(),
                'hora' => $datos['hora'] ?? '08:00',
                'duracion_estimada' => $datos['duracion_estimada'] ?? 120,
                'prioridad' => $datos['prioridad'] ?? 'media',
                'descripcion' => $datos['descripcion'] ?? trim("Cotización {$cotizacion->numero} aprobada: {$cotizacion->titulo}.
{$renglones}"),
                'tecnicos' => $datos['tecnicos'] ?? [],
            ]);
            $cotizacion->update(['orden_id' => $orden->id]);
            $cotizacion->equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'cotizacion_orden',
                'descripcion' => "Cotización {$cotizacion->numero}: se programó la orden {$orden->numero}.",
            ]);

            return $orden;
        });
    }

    /** Número consecutivo por año (CT-2026-00001). */
    private function siguienteNumero(): string
    {
        $anio = now()->year;
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32("cotizaciones-{$anio}")]);
        }
        $ultimo = Cotizacion::where('numero', 'like', "CT-{$anio}-%")->orderByDesc('numero')->value('numero');

        return sprintf('CT-%d-%05d', $anio, $ultimo ? ((int) substr($ultimo, -5)) + 1 : 1);
    }
}
