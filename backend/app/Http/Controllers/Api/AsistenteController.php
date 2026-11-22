<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Asistente\AsistenteService;
use App\Services\Asistente\HerramientasAsistente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Chat del asistente: el del panel (con sesión y según el rol) y el de la página pública. */
class AsistenteController extends Controller
{
    public function __construct(private readonly AsistenteService $asistente) {}

    /** Con sesión: texto y, opcionalmente, una foto del ascensor (cámara o galería) para analizar. */
    public function panel(Request $solicitud): JsonResponse
    {
        $datos = $solicitud->validate([
            'mensaje' => ['nullable', 'required_without:imagen', 'string', 'max:1000'],
            'sesion' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'imagen' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], ['imagen.mimes' => 'La foto debe ser JPG, PNG o WEBP.', 'imagen.max' => 'La foto no puede pesar más de 8 MB.'], ['mensaje' => 'mensaje', 'imagen' => 'foto']);

        return response()->json(['datos' => $this->asistente->panel($solicitud->user(), (string) ($datos['mensaje'] ?? ''), $datos['sesion'], $solicitud->file('imagen'))]);
    }

    public function publico(Request $solicitud): JsonResponse
    {
        $datos = $this->validar($solicitud);

        return response()->json(['datos' => $this->asistente->publico($datos['mensaje'], $datos['sesion'])]);
    }

    /** Lo llama el agente de n8n con el token temporal del usuario (habilidad «asistente»). */
    public function herramienta(Request $solicitud): JsonResponse
    {
        $token = $solicitud->user()->currentAccessToken();
        abort_unless($token && ($token->name ?? null) === 'asistente-n8n' && $token->can('asistente'), 403, 'Solo el agente del asistente usa esta ruta.');
        $datos = $solicitud->validate([
            'accion' => ['required', 'string', Rule::in(array_keys(HerramientasAsistente::ACCIONES))],
            'parametros' => ['nullable', 'array'],
            'parametros.*' => ['nullable', 'scalar', 'max:200'],
        ]);

        return response()->json(['datos' => $this->asistente->herramienta($solicitud->user(), $token->id, $datos['accion'], $datos['parametros'] ?? [])]);
    }

    private function validar(Request $solicitud): array
    {
        return $solicitud->validate([
            'mensaje' => ['required', 'string', 'max:1000'],
            'sesion' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ], [], ['mensaje' => 'mensaje']);
    }
}
