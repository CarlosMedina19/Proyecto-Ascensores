<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Models\ChecklistItem;
use App\Models\ChecklistSeccion;
use App\Services\RegistroAuditoriaService;
use App\Services\VisitaTecnicaService;
use App\Support\PlantillaPlanilla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Plantilla de la «Planilla de asistencia técnica». Todos la consultan (el técnico la diligencia);
 * solo quien edita la configuración (administrador) agrega, cambia, ordena o quita secciones y actividades.
 * Lo que se quita y ya tiene planillas diligenciadas queda inactivo para conservar el historial.
 * Una sección también se puede desactivar y volver a activar (botón): conserva sus actividades.
 */
class PlantillaPlanillaController extends Controller
{
    public function __construct(private readonly VisitaTecnicaService $visitas) {}

    /** ?todas=1 (solo quien edita la configuración) incluye las secciones desactivadas. */
    public function index(Request $solicitud): JsonResponse
    {
        $todas = $solicitud->boolean('todas') && $solicitud->user()->tienePermiso('configuracion.editar');

        return response()->json(['datos' => $this->visitas->plantilla($todas), 'base' => PlantillaPlanilla::totalActividades()]);
    }

    public function guardarSeccion(Request $solicitud): JsonResponse
    {
        $this->exigirEdicion($solicitud);
        $nombre = $solicitud->validate(['nombre' => ['required', 'string', 'max:100']])['nombre'];
        $seccion = ChecklistSeccion::create([
            'clave' => $this->claveUnica($nombre),
            'nombre' => trim($nombre),
            'orden' => (int) ChecklistSeccion::max('orden') + 1,
            'activo' => true,
        ]);
        RegistroAuditoriaService::registrar('creado', $seccion, $seccion->only(['clave', 'nombre']), null, 'configuracion');

        return $this->respuesta('Sección agregada.', 201);
    }

    public function actualizarSeccion(Request $solicitud, ChecklistSeccion $seccion): JsonResponse
    {
        $this->exigirEdicion($solicitud);
        $datos = $solicitud->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:100'],
            'mover' => ['sometimes', 'in:arriba,abajo'],
            'habilitada' => ['sometimes', 'boolean'],
        ]);
        abort_unless($seccion->activo, 404);
        if (isset($datos['nombre'])) {
            $seccion->update(['nombre' => trim($datos['nombre'])]);
        }
        if (array_key_exists('habilitada', $datos) && (bool) $datos['habilitada'] !== $seccion->habilitada) {
            if (! $datos['habilitada'] && $this->ultimaEnUso($seccion)) {
                throw new ReglaNegocioException('La planilla debe tener al menos una sección activa.');
            }
            $seccion->update(['habilitada' => (bool) $datos['habilitada']]);
            RegistroAuditoriaService::registrar('actualizado', $seccion, ['habilitada' => $seccion->habilitada], null, 'configuracion');

            return $this->respuesta($seccion->habilitada
                ? "Sección «{$seccion->nombre}» activada: vuelve a las planillas nuevas."
                : "Sección «{$seccion->nombre}» desactivada: no aparece en las planillas nuevas. Sus actividades se conservan.");
        }
        if (isset($datos['mover'])) {
            $this->mover(ChecklistSeccion::where('activo', true)->orderBy('orden')->orderBy('id')->get(), $seccion->id, $datos['mover']);
        }
        RegistroAuditoriaService::registrar('actualizado', $seccion, $datos, null, 'configuracion');

        return $this->respuesta('Sección actualizada.');
    }

    public function eliminarSeccion(Request $solicitud, ChecklistSeccion $seccion): JsonResponse
    {
        $this->exigirEdicion($solicitud);
        abort_unless($seccion->activo, 404);
        if ($this->ultimaEnUso($seccion)) {
            throw new ReglaNegocioException('La planilla debe tener al menos una sección activa.');
        }
        DB::transaction(function () use ($seccion) {
            foreach (ChecklistItem::where('seccion', $seccion->clave)->get() as $item) {
                $this->retirarItem($item);
            }
            $seccion->update(['activo' => false]);
        });
        RegistroAuditoriaService::registrar('eliminado', $seccion, ['nombre' => $seccion->nombre], null, 'configuracion');

        return $this->respuesta('Sección quitada de la planilla.');
    }

    public function guardarItem(Request $solicitud): JsonResponse
    {
        $this->exigirEdicion($solicitud);
        $datos = $solicitud->validate([
            'seccion_id' => ['required', 'integer', 'exists:checklist_secciones,id'],
            'nombre' => ['required', 'string', 'max:200'],
            'critico' => ['sometimes', 'boolean'],
        ]);
        $seccion = ChecklistSeccion::where('activo', true)->findOrFail($datos['seccion_id']);
        $item = ChecklistItem::create([
            'seccion' => $seccion->clave,
            'codigo' => $this->codigoUnico($seccion),
            'nombre' => trim($datos['nombre']),
            'orden' => (int) ChecklistItem::where('seccion', $seccion->clave)->where('activo', true)->max('orden') + 1,
            'critico' => (bool) ($datos['critico'] ?? false),
            'activo' => true,
        ]);
        RegistroAuditoriaService::registrar('creado', $item, $item->only(['seccion', 'codigo', 'nombre', 'critico']), null, 'configuracion');

        return $this->respuesta('Actividad agregada.', 201);
    }

    public function actualizarItem(Request $solicitud, ChecklistItem $item): JsonResponse
    {
        $this->exigirEdicion($solicitud);
        abort_unless($item->activo, 404);
        $datos = $solicitud->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:200'],
            'critico' => ['sometimes', 'boolean'],
            'mover' => ['sometimes', 'in:arriba,abajo'],
        ]);
        $item->update(collect($datos)->only(['nombre', 'critico'])->map(fn ($v) => is_string($v) ? trim($v) : $v)->all());
        if (isset($datos['mover'])) {
            $this->mover(ChecklistItem::where('seccion', $item->seccion)->where('activo', true)->orderBy('orden')->orderBy('id')->get(), $item->id, $datos['mover']);
        }
        RegistroAuditoriaService::registrar('actualizado', $item, $datos, null, 'configuracion');

        return $this->respuesta('Actividad actualizada.');
    }

    public function eliminarItem(Request $solicitud, ChecklistItem $item): JsonResponse
    {
        $this->exigirEdicion($solicitud);
        abort_unless($item->activo, 404);
        $this->retirarItem($item);
        RegistroAuditoriaService::registrar('eliminado', $item, ['nombre' => $item->nombre], null, 'configuracion');

        return $this->respuesta('Actividad quitada de la planilla.');
    }

    /** Vuelve a la planilla original (las actividades actuales quedan inactivas, con su historial). */
    public function restablecer(Request $solicitud): JsonResponse
    {
        $this->exigirEdicion($solicitud);
        DB::transaction(function () {
            ChecklistItem::query()->update(['activo' => false]);
            ChecklistSeccion::query()->update(['activo' => false]);
            $orden = 0;
            foreach (PlantillaPlanilla::SECCIONES as $clave => [$nombre, $actividades]) {
                ChecklistSeccion::updateOrCreate(['clave' => $clave], ['nombre' => $nombre, 'orden' => ++$orden, 'activo' => true, 'habilitada' => true]);
                foreach ($actividades as $indice => [$codigo, $actividad, $critica]) {
                    ChecklistItem::updateOrCreate(['codigo' => $codigo], ['seccion' => $clave, 'nombre' => $actividad, 'orden' => $indice + 1, 'critico' => $critica, 'activo' => true]);
                }
            }
        });
        RegistroAuditoriaService::registrar('actualizado', ChecklistSeccion::firstOrFail(), ['plantilla' => 'restablecida'], null, 'configuracion');

        return $this->respuesta('Se restableció la planilla original.');
    }

    /** ¿Es la única sección que hoy se diligencia? */
    private function ultimaEnUso(ChecklistSeccion $seccion): bool
    {
        return ChecklistSeccion::enUso()->whereKeyNot($seccion->id)->doesntExist();
    }

    private function exigirEdicion(Request $solicitud): void
    {
        abort_unless($solicitud->user()->tienePermiso('configuracion.editar'), 403, 'Solo el administrador edita la planilla.');
    }

    private function respuesta(string $mensaje, int $estado = 200): JsonResponse
    {
        return response()->json(['datos' => $this->visitas->plantilla(true), 'mensaje' => $mensaje], $estado);
    }

    /** Sin planillas que la usen se borra; si ya se usó, queda inactiva (el historial no cambia). */
    private function retirarItem(ChecklistItem $item): void
    {
        $usado = DB::table('planilla_resultados')->where('item_id', $item->id)->exists()
            || DB::table('planilla_fotos')->where('item_id', $item->id)->exists();
        $usado ? $item->update(['activo' => false]) : $item->delete();
    }

    /** Intercambia el orden con el vecino de arriba o de abajo y renumera. */
    private function mover($lista, int $id, string $direccion): void
    {
        $lista = $lista->values();
        $posicion = $lista->search(fn ($m) => $m->id === $id);
        $destino = $direccion === 'arriba' ? $posicion - 1 : $posicion + 1;
        if ($posicion === false || $destino < 0 || $destino >= $lista->count()) {
            return;
        }
        $orden = $lista->all();
        [$orden[$posicion], $orden[$destino]] = [$orden[$destino], $orden[$posicion]];
        DB::transaction(function () use ($orden) {
            foreach (array_values($orden) as $indice => $modelo) {
                $modelo->update(['orden' => $indice + 1]);
            }
        });
    }

    private function claveUnica(string $nombre): string
    {
        $base = Str::limit(Str::slug($nombre, '_'), 30, '') ?: 'seccion';
        $clave = $base;
        for ($i = 2; ChecklistSeccion::where('clave', $clave)->exists(); $i++) {
            $clave = "{$base}_{$i}";
        }

        return $clave;
    }

    /** Código legible: prefijo de la sección (MQ, CB…) y el siguiente número libre. */
    private function codigoUnico(ChecklistSeccion $seccion): string
    {
        $existente = ChecklistItem::where('seccion', $seccion->clave)->orderBy('id')->value('codigo');
        $prefijo = $existente && str_contains($existente, '-') ? Str::before($existente, '-') : Str::upper(Str::substr(Str::ascii($seccion->clave), 0, 2));
        $numero = ChecklistItem::where('codigo', 'like', "{$prefijo}-%")->count() + 1;
        do {
            $codigo = sprintf('%s-%02d', $prefijo, $numero++);
        } while (ChecklistItem::where('codigo', $codigo)->exists());

        return $codigo;
    }
}
