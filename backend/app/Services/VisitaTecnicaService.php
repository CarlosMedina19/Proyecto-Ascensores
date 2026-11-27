<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\ChecklistItem;
use App\Models\ChecklistSeccion;
use App\Models\Equipo;
use App\Models\OrdenTrabajo;
use App\Models\Usuario;
use App\Models\VisitaTecnica;
use App\Notifications\VisitaTecnicaNotification;
use App\Support\Archivos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Planilla de asistencia técnica de una visita: valida lo marcado contra la plantilla vigente, guarda las
 * firmas, genera el PDF con el formato de la planilla en papel y deja el registro en el historial.
 */
class VisitaTecnicaService
{
    /** Las planillas en papel venían numeradas; las digitales siguen desde aquí. */
    private const NUMERO_INICIAL = 12900;

    private const PESO_MAXIMO_FIRMA = 512 * 1024;

    public function __construct(
        private readonly VisitaService $visitas,
        private readonly EquipoService $equipos,
        private readonly NotificacionService $notificaciones,
    ) {}

    /**
     * Plantilla vigente: secciones activas con sus actividades activas.
     *
     * @return array<int, array{id: int, clave: string, nombre: string, items: array<int, array{id: int, codigo: string, nombre: string, critico: bool}>}>
     */
    /**
     * Secciones con sus actividades. Las visitas solo ven las secciones en uso; la configuración ($conDesactivadas)
     * también las desactivadas, para poder activarlas de nuevo.
     */
    public function plantilla(bool $conDesactivadas = false): array
    {
        $items = ChecklistItem::where('activo', true)->orderBy('orden')->orderBy('id')->get()->groupBy('seccion');

        return ChecklistSeccion::where('activo', true)->when(! $conDesactivadas, fn ($c) => $c->where('habilitada', true))
            ->orderBy('orden')->orderBy('id')->get()
            ->map(fn (ChecklistSeccion $s) => [
                'id' => $s->id,
                'clave' => $s->clave,
                'nombre' => $s->nombre,
                'habilitada' => $s->habilitada,
                'items' => ($items[$s->clave] ?? collect())->map(fn (ChecklistItem $i) => [
                    'id' => $i->id,
                    'codigo' => $i->codigo,
                    'nombre' => $i->nombre,
                    'critico' => $i->critico,
                ])->values()->all(),
            ])->values()->all();
    }

    public function registrar(Equipo $equipo, Usuario $usuario, array $datos): VisitaTecnica
    {
        $this->visitas->exigir($equipo, $usuario);
        $equipo->loadMissing('edificio');
        $secciones = $this->diligenciar($datos['resultados']);
        $firmaTecnico = $this->png($datos['firma_tecnico'], 'del técnico');
        $firmaRecibe = $this->png($datos['firma_recibe'], 'de quien recibe');
        // La planilla pudo diligenciarse sin conexión y enviarse después: valen las horas del celular,
        // sin pasar de la hora del servidor.
        $salida = isset($datos['salida_en']) ? Carbon::parse($datos['salida_en'])->min(now()) : now();
        $entrada = Carbon::parse($datos['entrada_en'])->min(now());
        if ($entrada->greaterThan($salida)) {
            throw new ReglaNegocioException('La hora de entrada no puede ser posterior a la de salida.');
        }

        $visita = DB::transaction(function () use ($equipo, $usuario, $datos, $secciones, $firmaTecnico, $firmaRecibe, $entrada, $salida) {
            $carpeta = "visitas/{$equipo->identificador_uuid}";
            $rutaTecnico = "{$carpeta}/firmas/tecnico-".Str::random(12).'.png';
            $rutaRecibe = "{$carpeta}/firmas/recibe-".Str::random(12).'.png';
            Archivos::disco()->put($rutaTecnico, $firmaTecnico);
            Archivos::disco()->put($rutaRecibe, $firmaRecibe);

            $orden = OrdenTrabajo::where('equipo_id', $equipo->id)->whereIn('estado', ['en_camino', 'en_proceso', 'pendiente'])
                ->whereHas('tecnicos', fn ($t) => $t->where('usuario_id', $usuario->id))->orderByDesc('id')->first();

            $visita = VisitaTecnica::create([
                'equipo_id' => $equipo->id,
                'edificio_id' => $equipo->edificio_id,
                'tecnico_usuario_id' => $usuario->id,
                'orden_id' => $orden?->id,
                'tipo_servicio' => $datos['tipo_servicio'],
                'entrada_en' => $entrada,
                'salida_en' => $salida,
                'secciones' => $secciones,
                'observaciones' => $datos['observaciones'] ?? null,
                'repuestos' => $datos['repuestos'] ?? [],
                'recibe_nombre' => $datos['recibe_nombre'],
                'recibe_documento' => $datos['recibe_documento'],
                'tecnico_documento' => $datos['tecnico_documento'] ?? $usuario->tecnico?->numero_documento,
                'firma_tecnico_ruta' => $rutaTecnico,
                'firma_recibe_ruta' => $rutaRecibe,
            ]);
            $visita->numero = (string) (self::NUMERO_INICIAL + $visita->id);
            $visita->pdf_ruta = "{$carpeta}/planilla-{$visita->numero}.pdf";
            Archivos::disco()->put($visita->pdf_ruta, $this->pdf($visita->setRelation('equipo', $equipo)->setRelation('tecnico', $usuario)));
            $visita->save();

            $conteo = $visita->conteo();
            $this->equipos->agregarHistorial($equipo, [
                'evento' => 'mantenimiento',
                'descripcion' => "Planilla de asistencia técnica N.º {$visita->numero} · ".VisitaTecnica::TIPOS[$visita->tipo_servicio]
                    ." · {$conteo['B']} B, {$conteo['R']} R, {$conteo['M']} M, {$conteo['NA']} N.A."
                    .($visita->observaciones ? " · {$visita->observaciones}" : ''),
            ]);
            RegistroAuditoriaService::registrar('creado', $visita, ['numero' => $visita->numero, 'equipo' => $equipo->codigo], null, 'equipos');

            return $visita;
        });

        try {
            $destinatarios = $this->notificaciones->administradores()->merge($this->notificaciones->usuariosCliente($equipo->edificio?->cliente_id))->unique('id');
            $this->notificaciones->enviar($destinatarios, new VisitaTecnicaNotification($visita->loadMissing(['equipo', 'edificio', 'tecnico'])), 'visita_tecnica', 'visita', $visita->id);
        } catch (Throwable $e) {
            Log::warning('Visita técnica: no se pudo notificar', ['visita' => $visita->id, 'error' => $e->getMessage()]);
        }

        return $visita;
    }

    /**
     * Cruza lo enviado con la plantilla vigente: cada actividad debe quedar en B, R, M o N.A.;
     * R y M exigen observación. Devuelve la copia que se guarda con la visita.
     */
    private function diligenciar(array $resultados): array
    {
        $porItem = collect($resultados)->keyBy(fn ($r) => (int) $r['item_id']);
        $faltan = [];
        $sinObservacion = [];
        $secciones = [];
        foreach ($this->plantilla() as $seccion) {
            $items = [];
            foreach ($seccion['items'] as $item) {
                $r = $porItem->get($item['id']);
                $na = (bool) ($r['na'] ?? false);
                $estado = $na ? null : ($r['estado'] ?? null);
                if (! $na && ! in_array($estado, ['B', 'R', 'M'], true)) {
                    $faltan[] = $item['nombre'];

                    continue;
                }
                $observacion = trim((string) ($r['observacion'] ?? '')) ?: null;
                if (in_array($estado, ['R', 'M'], true) && ! $observacion) {
                    $sinObservacion[] = $item['nombre'];
                }
                $items[] = [
                    'item_id' => $item['id'],
                    'codigo' => $item['codigo'],
                    'nombre' => $item['nombre'],
                    'critico' => $item['critico'],
                    'ins' => ! $na && (bool) ($r['ins'] ?? true),
                    'estado' => $estado,
                    'na' => $na,
                    'observacion' => $observacion,
                ];
            }
            $secciones[] = ['clave' => $seccion['clave'], 'nombre' => $seccion['nombre'], 'items' => $items];
        }

        if ($faltan) {
            throw new ReglaNegocioException('Faltan '.count($faltan).' actividad(es) por marcar (B, R, M o N.A.). Si la planilla cambió, actualícela en la app. Ej.: '.Str::limit($faltan[0], 80).'.');
        }
        if ($sinObservacion) {
            throw new ReglaNegocioException('Las actividades en Regular o Malo necesitan una observación: '.Str::limit(implode('; ', $sinObservacion), 160).'.');
        }

        return $secciones;
    }

    private function png(string $imagen, string $quien): string
    {
        $binario = base64_decode((string) preg_replace('#^data:image/png;base64,#', '', $imagen), true);
        if ($binario === false || ! str_starts_with($binario, "\x89PNG\r\n\x1a\n")) {
            throw new ReglaNegocioException("La firma {$quien} debe ser una imagen PNG.");
        }
        if (strlen($binario) > self::PESO_MAXIMO_FIRMA) {
            throw new ReglaNegocioException("La imagen de la firma {$quien} es demasiado grande.");
        }

        return $binario;
    }

    /** PDF con el formato de la «Planilla de asistencia técnica». */
    public function pdf(VisitaTecnica $visita): string
    {
        $visita->loadMissing(['equipo.edificio', 'tecnico']);
        $imagen = function (?string $ruta): ?string {
            if (! $ruta || ! Archivos::disco()->exists($ruta)) {
                return null;
            }

            return 'data:image/png;base64,'.base64_encode(Archivos::disco()->get($ruta));
        };
        // Columnas equilibradas: las secciones se reparten según su número de actividades.
        $total = collect($visita->secciones)->sum(fn ($s) => count($s['items']) + 2);
        $izquierda = [];
        $derecha = [];
        $acumulado = 0;
        foreach ($visita->secciones as $seccion) {
            if ($acumulado < $total / 2) {
                $izquierda[] = $seccion;
                $acumulado += count($seccion['items']) + 2;
            } else {
                $derecha[] = $seccion;
            }
        }

        $html = view('pdf.planilla-visita', [
            'visita' => $visita,
            'columnas' => [$izquierda, $derecha],
            'logo' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(resource_path('imagenes/sigma-icono.png'))),
            'firmaTecnico' => $imagen($visita->firma_tecnico_ruta),
            'firmaRecibe' => $imagen($visita->firma_recibe_ruta),
            'entidad' => config('sigma.planilla_entidad', 'Centro de Industria y Construcción – SENA Picaleña'),
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('letter')
            ->setOption('dpi', 96)
            ->setOption('isFontSubsettingEnabled', false)
            ->setOption('fontDir', storage_path('fonts'))
            ->setOption('fontCache', storage_path('fonts'))
            ->output();
    }
}
