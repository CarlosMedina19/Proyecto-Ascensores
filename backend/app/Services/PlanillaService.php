<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\ChecklistItem;
use App\Models\FirmaOrden;
use App\Models\OrdenTrabajo;
use App\Models\Planilla;
use App\Models\PlanillaFoto;
use App\Models\PlanillaResultado;
use App\Notifications\ChecklistNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * RF-030 a RF-038: planilla digital del mantenimiento preventivo, fotos, firmas y correctivo derivado.
 */
class PlanillaService
{
    private const DISCO = 'local';

    /** Firma en PNG de máximo 500 KB. */
    private const PESO_MAXIMO_FIRMA = 512000;

    public function __construct(
        private readonly NotificacionService $notificaciones,
        private readonly CalendarioHabilService $calendario,
    ) {}

    /** @return Collection<int, ChecklistItem> */
    public function catalogo(): Collection
    {
        $orden = array_keys(ChecklistItem::SECCIONES);

        return ChecklistItem::where('activo', true)
            ->get()
            ->sortBy(fn (ChecklistItem $i) => [array_search($i->seccion, $orden, true), $i->orden])
            ->values();
    }

    /**
     * RF-030 y RF-037: guarda los resultados (uno o varios) y las recomendaciones.
     * Solo en una orden preventiva en proceso. Los ítems críticos en M avisan de inmediato.
     *
     * @param  array{resultados?: array<int, array{item_id: int, resultado: string, estado: string, observacion?: ?string}>, recomendaciones?: ?string}  $datos
     */
    public function guardar(OrdenTrabajo $orden, array $datos): Planilla
    {
        $this->exigirEnProceso($orden);

        return DB::transaction(function () use ($orden, $datos) {
            $planilla = Planilla::firstOrCreate(['orden_id' => $orden->id], [
                'tecnico_usuario_id' => Auth::id(),
                'iniciada_en' => now(),
            ]);

            if (array_key_exists('recomendaciones', $datos)) {
                $planilla->update(['recomendaciones' => $datos['recomendaciones']]);
            }

            $items = ChecklistItem::whereKey(collect($datos['resultados'] ?? [])->pluck('item_id'))->get()->keyBy('id');
            $criticos = [];

            foreach ($datos['resultados'] ?? [] as $fila) {
                $item = $items->get($fila['item_id']);
                if (! $item || ! $item->activo) {
                    throw new ReglaNegocioException('Uno de los ítems no pertenece a la planilla.');
                }

                $resultado = PlanillaResultado::firstOrNew(['planilla_id' => $planilla->id, 'item_id' => $item->id]);
                $eraMalo = $resultado->exists && $resultado->estado === 'M';
                $resultado->fill([
                    'resultado' => $fila['resultado'],
                    'estado' => $fila['estado'],
                    'observacion' => $fila['estado'] === 'B' ? ($fila['observacion'] ?? null) : trim((string) ($fila['observacion'] ?? '')),
                    'actualizado_por' => Auth::id(),
                ]);
                if ($resultado->estado !== 'M') {
                    $resultado->notificado_en = null;
                }
                $resultado->save();

                // RF-032 y RF-036: falla de seguridad o emergencia → aviso inmediato (una sola vez).
                if ($item->critico && $resultado->estado === 'M' && (! $eraMalo || ! $resultado->notificado_en)) {
                    $criticos[] = $resultado;
                }
            }

            if ($criticos) {
                $this->avisar($orden, $criticos, true);
            }

            RegistroAuditoriaService::registrar('planilla_actualizada', $orden, [
                'items' => collect($datos['resultados'] ?? [])->map(fn ($f) => "{$f['item_id']}:{$f['resultado']}/{$f['estado']}")->all(),
            ], null, 'ordenes');

            return $this->detalle($planilla);
        });
    }

    /** Ítems activos que aún no tienen resultado (RF-030: todos deben tenerlo para finalizar). */
    public function faltantes(?Planilla $planilla): int
    {
        $total = ChecklistItem::where('activo', true)->count();
        if (! $planilla) {
            return $total;
        }

        return $total - $planilla->resultados()->whereHas('item', fn ($i) => $i->where('activo', true))->count();
    }

    /** Al finalizar: marca la planilla completa y avisa los ítems en M que faltaban por avisar. */
    public function completar(OrdenTrabajo $orden): void
    {
        $planilla = $orden->planilla;
        if (! $planilla) {
            return;
        }

        $planilla->update(['completada_en' => now()]);
        $pendientes = $planilla->resultados()->with('item')->where('estado', 'M')->whereNull('notificado_en')->get();
        if ($pendientes->isNotEmpty()) {
            $this->avisar($orden, $pendientes->all(), false);
        }
    }

    // ------------------------------------------------------------------ fotos

    public function agregarFoto(OrdenTrabajo $orden, UploadedFile $archivo, ?int $itemId, ?string $descripcion): PlanillaFoto
    {
        $this->exigirEnProceso($orden);

        return DB::transaction(function () use ($orden, $archivo, $itemId, $descripcion) {
            $planilla = Planilla::firstOrCreate(['orden_id' => $orden->id], ['tecnico_usuario_id' => Auth::id(), 'iniciada_en' => now()]);
            Planilla::whereKey($planilla->id)->lockForUpdate()->first();

            if ($planilla->fotos()->count() >= PlanillaFoto::MAXIMO_POR_PLANILLA) {
                throw new ReglaNegocioException('La planilla admite máximo '.PlanillaFoto::MAXIMO_POR_PLANILLA.' fotos.');
            }

            return PlanillaFoto::create([
                'planilla_id' => $planilla->id,
                'item_id' => $itemId,
                'ruta' => $archivo->store("ordenes/{$orden->identificador_uuid}/fotos", self::DISCO),
                'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
                'tipo_mime' => $archivo->getMimeType(),
                'tamano' => $archivo->getSize(),
                'descripcion' => $descripcion,
                'usuario_id' => Auth::id(),
            ]);
        });
    }

    public function eliminarFoto(OrdenTrabajo $orden, PlanillaFoto $foto): void
    {
        $this->exigirEnProceso($orden);
        $foto->delete();
        Storage::disk(self::DISCO)->delete($foto->ruta);
    }

    // ------------------------------------------------------------------ firmas

    /**
     * RF-025 y RF-039: firma del técnico o de quien recibe el servicio (PNG en base64).
     * Se puede repetir mientras la orden no esté finalizada (reemplaza la anterior).
     */
    public function firmar(OrdenTrabajo $orden, array $datos): FirmaOrden
    {
        if (! in_array($orden->estado, ['en_proceso', 'pendiente'], true)) {
            throw new ReglaNegocioException('Las firmas se registran mientras la orden está en proceso.');
        }

        $png = $this->decodificarPng($datos['imagen']);

        return DB::transaction(function () use ($orden, $datos, $png) {
            $ruta = "ordenes/{$orden->identificador_uuid}/firmas/{$datos['tipo']}-".Str::random(12).'.png';
            Storage::disk(self::DISCO)->put($ruta, $png);

            $anterior = FirmaOrden::where('orden_id', $orden->id)->where('tipo', $datos['tipo'])->first();
            $firma = FirmaOrden::updateOrCreate(['orden_id' => $orden->id, 'tipo' => $datos['tipo']], [
                'nombre' => $datos['nombre'],
                'documento' => $datos['documento'] ?? null,
                'cargo' => $datos['cargo'] ?? null,
                'ruta' => $ruta,
                'registrada_por' => Auth::id(),
                'firmado_en' => now(),
            ]);
            if ($anterior) {
                Storage::disk(self::DISCO)->delete($anterior->ruta);
            }

            return $firma;
        });
    }

    // ------------------------------------------------------------------ correctivo derivado

    /**
     * RF-038: crea una orden correctiva desde un ítem en R o M, vinculada al ítem y a la orden.
     * No se crean dos correctivos abiertos para el mismo ítem.
     */
    public function crearCorrectivo(OrdenTrabajo $orden, PlanillaResultado $resultado, array $datos, OrdenTrabajoService $ordenes): OrdenTrabajo
    {
        if ($resultado->planilla?->orden_id !== $orden->id) {
            throw new ReglaNegocioException('El ítem no pertenece a la planilla de esta orden.');
        }
        if (! in_array($resultado->estado, ['R', 'M'], true)) {
            throw new ReglaNegocioException('Solo los ítems en estado Regular o Malo generan una orden correctiva.');
        }
        $abierto = $resultado->correctivoAbierto()->value('numero');
        if ($abierto) {
            throw new ReglaNegocioException("El ítem ya tiene el correctivo abierto {$abierto}.");
        }

        $resultado->loadMissing('item');
        // Por defecto, el siguiente día hábil a las 08:00; el coordinador puede reprogramar.
        $fecha = $datos['fecha'] ?? $this->calendario->siguienteHabil(CarbonImmutable::tomorrow())->toDateString();

        return $ordenes->crear([
            'servicio' => 'correctivo',
            'equipo_id' => $orden->equipo_id,
            'edificio_id' => $orden->edificio_id,
            'cliente_id' => $orden->cliente_id,
            'fecha' => $fecha,
            'hora' => $datos['hora'] ?? '08:00',
            'duracion_estimada' => $datos['duracion_estimada'] ?? 120,
            'prioridad' => $datos['prioridad'] ?? ($resultado->estado === 'M' ? 'alta' : 'media'),
            'descripcion' => $datos['descripcion']
                ?? "{$resultado->item->nombreSeccion()} · {$resultado->item->nombre} en estado "
                    .($resultado->estado === 'M' ? 'Malo' : 'Regular').": {$resultado->observacion}",
            'tecnicos' => $datos['tecnicos'] ?? [],
            'orden_origen_id' => $orden->id,
            'resultado_origen_id' => $resultado->id,
        ]);
    }

    // ------------------------------------------------------------------ resumen y apoyo

    public function detalle(Planilla $planilla): Planilla
    {
        return $planilla->fresh(['resultados.item', 'resultados.correctivoAbierto', 'resultados.correctivos', 'fotos', 'tecnico']);
    }

    /** Estado más grave por sección de la última planilla completa del ascensor (para el esquema). */
    public function ultimaRevision(int $equipoId): ?array
    {
        $planilla = Planilla::with(['resultados.item', 'orden'])
            ->whereNotNull('completada_en')
            ->whereHas('orden', fn ($o) => $o->where('equipo_id', $equipoId))
            ->latest('completada_en')
            ->first();

        if (! $planilla) {
            return null;
        }

        $peor = fn ($estados) => $estados->contains('M') ? 'M' : ($estados->contains('R') ? 'R' : ($estados->isEmpty() ? null : 'B'));
        $secciones = [];
        foreach (array_keys(ChecklistItem::SECCIONES) as $seccion) {
            $secciones[$seccion] = $peor($planilla->resultados->filter(fn ($r) => $r->item?->seccion === $seccion)->pluck('estado'));
        }

        return [
            'fecha' => $planilla->completada_en->toIso8601String(),
            'orden_id' => $planilla->orden_id,
            'orden_numero' => $planilla->orden?->numero,
            'secciones' => $secciones,
        ];
    }

    private function exigirEnProceso(OrdenTrabajo $orden): void
    {
        if (! $orden->llevaPlanilla()) {
            throw new ReglaNegocioException('La planilla de mantenimiento solo se diligencia en órdenes preventivas.');
        }
        if ($orden->estado !== 'en_proceso') {
            throw new ReglaNegocioException('La planilla se diligencia con la orden en proceso.');
        }
    }

    /** @param  array<int, PlanillaResultado>  $resultados */
    private function avisar(OrdenTrabajo $orden, array $resultados, bool $inmediato): void
    {
        $orden->loadMissing(['equipo', 'edificio']);
        $items = array_map(
            fn (PlanillaResultado $r) => "{$r->item->nombreSeccion()} · {$r->item->nombre}: {$r->observacion}",
            $resultados
        );
        $this->notificaciones->enviar(
            $this->notificaciones->coordinadores(),
            new ChecklistNotification($orden, $items, $inmediato),
            ($inmediato ? 'checklist_critico_' : 'checklist_malo_').now()->format('YmdHisv'),
            'orden',
            $orden->id,
        );
        PlanillaResultado::whereKey(array_map(fn ($r) => $r->id, $resultados))->update(['notificado_en' => now()]);
    }

    private function decodificarPng(string $imagen): string
    {
        $base64 = preg_replace('#^data:image/png;base64,#', '', $imagen);
        $binario = base64_decode((string) $base64, true);

        if ($binario === false || ! str_starts_with($binario, "\x89PNG\r\n\x1a\n")) {
            throw new ReglaNegocioException('La firma debe ser una imagen PNG.');
        }
        if (strlen($binario) > self::PESO_MAXIMO_FIRMA) {
            throw new ReglaNegocioException('La imagen de la firma es demasiado grande.');
        }

        return $binario;
    }
}
