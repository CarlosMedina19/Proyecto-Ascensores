<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\ActualizarConfiguracionRequest;
use App\Http\Resources\PeriodicidadResource;
use App\Models\Configuracion;
use App\Models\Periodicidad;
use App\Services\CalendarioHabilService;
use App\Services\RegistroAuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Parámetros del sistema (canales y días de aviso, alerta de contratos, IVA) y periodicidades.
 */
class ConfiguracionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['datos' => $this->parametros()]);
    }

    public function update(ActualizarConfiguracionRequest $solicitud): JsonResponse
    {
        $this->authorize('configurar');

        DB::transaction(function () use ($solicitud) {
            foreach ($solicitud->valores() as $clave => $valor) {
                $anterior = Configuracion::valor($clave);
                $registro = Configuracion::updateOrCreate(
                    ['clave' => $clave],
                    ['valor' => $valor, 'descripcion' => Configuracion::PARAMETROS[$clave]['descripcion'] ?? null]
                );
                if ($anterior != $valor) {
                    RegistroAuditoriaService::registrar('actualizado', $registro, [
                        $clave => ['anterior' => $anterior, 'nuevo' => $valor],
                    ], modulo: 'configuracion');
                }
            }
        });

        return response()->json([
            'datos' => $this->parametros(),
            'mensaje' => 'Configuración actualizada correctamente',
        ]);
    }

    /** RF-011: periodicidades disponibles (1 a 6 meses). */
    public function periodicidades(): AnonymousResourceCollection
    {
        return PeriodicidadResource::collection(Periodicidad::orderBy('meses')->get());
    }

    /** Festivos de Colombia de un año (para marcar el calendario). */
    public function festivos(Request $solicitud, CalendarioHabilService $calendario): JsonResponse
    {
        $anio = (int) $solicitud->validate(['anio' => ['required', 'integer', 'between:2000,2100']])['anio'];

        return response()->json([
            'datos' => collect($calendario->festivos($anio))
                ->map(fn (string $nombre, string $fecha) => ['fecha' => $fecha, 'nombre' => $nombre])
                ->values(),
        ]);
    }

    private function parametros(): array
    {
        return collect(Configuracion::PARAMETROS)
            ->map(fn (array $parametro, string $clave) => [
                'clave' => $clave,
                'valor' => Configuracion::valor($clave),
                'descripcion' => $parametro['descripcion'],
            ])
            ->values()
            ->all();
    }
}
