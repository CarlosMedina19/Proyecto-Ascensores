<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\ChecklistItem;
use App\Models\InformeOrden;
use App\Models\OrdenTrabajo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * RF-039: informe técnico en PDF con datos, checklist, observaciones, fotos, recomendaciones y firmas.
 * Un informe emitido no se modifica: cada corrección genera una versión nueva.
 */
class InformeService
{
    private const DISCO = 'local';

    /** Ancho máximo de las fotos dentro del PDF (se reducen para que el archivo no pese demasiado). */
    private const ANCHO_FOTO = 900;

    public function generar(OrdenTrabajo $orden, ?string $motivo = null): InformeOrden
    {
        $orden->loadMissing(['planilla']);
        if ($orden->estado !== 'finalizada') {
            throw new ReglaNegocioException('El informe se genera cuando la orden está finalizada.');
        }

        return DB::transaction(function () use ($orden, $motivo) {
            // Bloquea la orden para que dos solicitudes no creen la misma versión.
            OrdenTrabajo::whereKey($orden->id)->lockForUpdate()->first();
            $version = (int) InformeOrden::where('orden_id', $orden->id)->max('version') + 1;

            $contenido = $this->pdf($orden, $version, $motivo);
            $ruta = "ordenes/{$orden->identificador_uuid}/informes/{$orden->numero}-v{$version}.pdf";
            Storage::disk(self::DISCO)->put($ruta, $contenido);

            $informe = InformeOrden::create([
                'orden_id' => $orden->id,
                'version' => $version,
                'ruta' => $ruta,
                'hash' => hash('sha256', $contenido),
                'tamano' => strlen($contenido),
                'motivo' => $motivo,
                'generado_por' => Auth::id(),
            ]);

            RegistroAuditoriaService::registrar('informe_generado', $orden, ['version' => $version, 'motivo' => $motivo], null, 'ordenes');

            return $informe;
        });
    }

    public function rutaAbsoluta(InformeOrden $informe): string
    {
        return Storage::disk(self::DISCO)->path($informe->ruta);
    }

    private function pdf(OrdenTrabajo $orden, int $version, ?string $motivo): string
    {
        $orden = $orden->fresh([
            'cliente', 'edificio', 'equipo.ascensor', 'tecnicos.usuario', 'firmas',
            'planilla.resultados.item', 'planilla.resultados.correctivos', 'planilla.fotos.item', 'planilla.tecnico',
        ]);

        $resultados = $orden->planilla?->resultados ?? collect();
        $secciones = [];
        foreach (ChecklistItem::SECCIONES as $clave => $nombre) {
            $filas = $resultados->filter(fn ($r) => $r->item?->seccion === $clave)->sortBy(fn ($r) => $r->item->orden)->values();
            if ($filas->isNotEmpty()) {
                $secciones[$nombre] = $filas;
            }
        }

        $html = view('pdf.informe-orden', [
            'orden' => $orden,
            'version' => $version,
            'motivo' => $motivo,
            'secciones' => $secciones,
            'conteo' => [
                'B' => $resultados->where('estado', 'B')->count(),
                'R' => $resultados->where('estado', 'R')->count(),
                'M' => $resultados->where('estado', 'M')->count(),
            ],
            'fotos' => ($orden->planilla?->fotos ?? collect())->map(fn ($f) => [
                'imagen' => $this->imagen($f->ruta),
                'texto' => trim(($f->item ? "{$f->item->nombre}. " : '').($f->descripcion ?? '')),
            ])->filter(fn ($f) => $f['imagen'])->values(),
            'firmas' => $orden->firmas->sortBy(fn ($f) => $f->tipo === 'tecnico' ? 0 : 1)->map(fn ($f) => [
                'tipo' => $f->tipo === 'tecnico' ? 'Técnico' : 'Recibe por el cliente',
                'nombre' => $f->nombre,
                'documento' => $f->documento,
                'cargo' => $f->cargo,
                'fecha' => $f->firmado_en,
                'imagen' => $this->imagen($f->ruta, false),
            ])->values(),
            'generadoPor' => Auth::user()?->nombre ?? 'Sistema',
        ])->render();

        // La plantilla usa fuentes estándar del PDF (Helvetica): no se incrustan ni se recortan,
        // así el informe sale en menos de un segundo y pesa pocos KB (más las fotos).
        return Pdf::loadHTML($html)->setPaper('letter')->setOption('dpi', 96)->output();
    }

    /** Imagen como data URI; las fotos se reducen y se pasan a JPEG. */
    private function imagen(string $ruta, bool $reducir = true): ?string
    {
        $disco = Storage::disk(self::DISCO);
        if (! $disco->exists($ruta)) {
            return null;
        }
        $datos = $disco->get($ruta);

        if (! $reducir || ! function_exists('imagecreatefromstring')) {
            return 'data:image/png;base64,'.base64_encode($datos);
        }

        $original = @imagecreatefromstring($datos);
        if (! $original) {
            return null;
        }
        $ancho = imagesx($original);
        $alto = imagesy($original);
        $escala = min(1, self::ANCHO_FOTO / max($ancho, 1));
        $reducida = imagescale($original, (int) max(1, $ancho * $escala), (int) max(1, $alto * $escala));
        ob_start();
        imagejpeg($reducida ?: $original, null, 78);
        $jpeg = ob_get_clean();

        return 'data:image/jpeg;base64,'.base64_encode($jpeg);
    }
}
