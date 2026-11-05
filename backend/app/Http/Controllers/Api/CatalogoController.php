<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Models\Ascensor;
use App\Models\Especialidad;
use App\Models\TipoAscensor;
use App\Services\RegistroAuditoriaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogos que el administrador mantiene en Configuración: especialidades de los técnicos y tipos de ascensor.
 */
class CatalogoController extends Controller
{
    private const CATALOGOS = [
        'especialidades' => ['modelo' => Especialidad::class, 'tabla' => 'especialidades', 'nombre' => 'La especialidad'],
        'tipos-ascensor' => ['modelo' => TipoAscensor::class, 'tabla' => 'tipos_ascensor', 'nombre' => 'El tipo de ascensor'],
    ];

    /** Tipos de ascensor activos, para los formularios. */
    public function tiposAscensor(): JsonResponse
    {
        return response()->json(['datos' => TipoAscensor::where('activo', true)->orderBy('nombre')->get(['id', 'clave', 'nombre'])]);
    }

    /** Los dos catálogos completos (activos e inactivos) con cuántos registros usan cada valor. */
    public function index(): JsonResponse
    {
        $this->authorize('configurar');
        $usoTipos = Ascensor::query()->whereNotNull('tipo_ascensor')->groupBy('tipo_ascensor')->selectRaw('tipo_ascensor, count(*) as total')->pluck('total', 'tipo_ascensor');

        return response()->json(['datos' => [
            'especialidades' => Especialidad::withCount('tecnicos')->orderBy('nombre')->get()
                ->map(fn ($e) => ['id' => $e->id, 'nombre' => $e->nombre, 'activo' => $e->activo, 'en_uso' => $e->tecnicos_count]),
            'tipos_ascensor' => TipoAscensor::orderBy('nombre')->get()
                ->map(fn ($t) => ['id' => $t->id, 'clave' => $t->clave, 'nombre' => $t->nombre, 'activo' => $t->activo, 'en_uso' => (int) ($usoTipos[$t->clave] ?? 0)]),
        ]]);
    }

    public function store(Request $solicitud, string $catalogo): JsonResponse
    {
        $this->authorize('configurar');
        $config = $this->config($catalogo);
        $datos = $solicitud->validate(['nombre' => ['required', 'string', 'max:100', Rule::unique($config['tabla'], 'nombre')]], [], ['nombre' => 'nombre']);
        $registro = $config['modelo']::create(['nombre' => trim($datos['nombre']), 'activo' => true]);
        RegistroAuditoriaService::registrar('creado', $registro, $registro->toArray(), null, 'configuracion');

        return response()->json(['datos' => $registro, 'mensaje' => "{$config['nombre']} «{$registro->nombre}» se agregó."], 201);
    }

    /** Cambia el nombre o activa / desactiva (lo inactivo deja de ofrecerse, pero lo ya registrado se conserva). */
    public function update(Request $solicitud, string $catalogo, int $id): JsonResponse
    {
        $this->authorize('configurar');
        $config = $this->config($catalogo);
        $registro = $config['modelo']::findOrFail($id);
        $datos = $solicitud->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:100', Rule::unique($config['tabla'], 'nombre')->ignore($registro->id)],
            'activo' => ['sometimes', 'boolean'],
        ]);
        $registro->update($datos);
        RegistroAuditoriaService::registrar('actualizado', $registro, $registro->getChanges(), null, 'configuracion');

        return response()->json(['datos' => $registro->fresh(), 'mensaje' => 'Cambios guardados.']);
    }

    public function destroy(string $catalogo, int $id): JsonResponse
    {
        $this->authorize('configurar');
        $config = $this->config($catalogo);
        /** @var Model $registro */
        $registro = $config['modelo']::findOrFail($id);
        $enUso = $registro instanceof Especialidad
            ? $registro->tecnicos()->exists()
            : Ascensor::where('tipo_ascensor', $registro->clave)->exists();
        if ($enUso) {
            throw new ReglaNegocioException("{$config['nombre']} «{$registro->nombre}» está en uso. Desactívelo para que no se ofrezca más.");
        }
        $registro->delete();
        RegistroAuditoriaService::registrar('eliminado', $registro, ['nombre' => $registro->nombre], null, 'configuracion');

        return response()->json(['exito' => true, 'mensaje' => "{$config['nombre']} «{$registro->nombre}» se eliminó."]);
    }

    private function config(string $catalogo): array
    {
        abort_unless(isset(self::CATALOGOS[$catalogo]), 404, 'Ese catálogo no existe.');

        return self::CATALOGOS[$catalogo];
    }
}
