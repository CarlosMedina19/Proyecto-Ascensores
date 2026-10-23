<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planilla\CorrectivoDerivadoRequest;
use App\Http\Requests\Servicio\GuardarCertificacionRequest;
use App\Http\Requests\Servicio\GuardarCotizacionRequest;
use App\Http\Requests\Servicio\ReportarEmergenciaRequest;
use App\Models\Certificacion;
use App\Models\Cotizacion;
use App\Models\Emergencia;
use App\Models\Equipo;
use App\Models\InformeOrden;
use App\Models\OrdenTrabajo;
use App\Models\Usuario;
use App\Http\Resources\OrdenTrabajoResource;
use App\Services\CalendarioHabilService;
use App\Services\CertificacionService;
use App\Services\CotizacionService;
use App\Services\EmergenciaService;
use App\Services\IndicadoresServicioService;
use App\Services\OrdenTrabajoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Servicio comercial: certificación anual, emergencias 24/7, cotizaciones de correctivos, indicadores
 * del servicio y documentos de cada ascensor.
 */
class ServicioController extends Controller
{
    public function __construct(
        private readonly CertificacionService $certificaciones,
        private readonly EmergenciaService $emergencias,
        private readonly CotizacionService $cotizaciones,
        private readonly IndicadoresServicioService $indicadores,
        private readonly OrdenTrabajoService $ordenes,
        private readonly CalendarioHabilService $calendario,
    ) {}

    /** La orden derivada (de una emergencia o de una cotización) exige crear órdenes y, con técnicos, asignarlas. */
    private function autorizarOrden(Request $solicitud): void
    {
        $this->authorize('create', OrdenTrabajo::class);
        if ($solicitud->filled('tecnicos')) {
            $this->authorize('asignar', new OrdenTrabajo);
        }
    }

    private function ordenCorta(?OrdenTrabajo $orden): ?array
    {
        return $orden ? ['id' => $orden->id, 'numero' => $orden->numero, 'estado' => $orden->estado, 'fecha' => $orden->fecha?->toDateString()] : null;
    }

    private function exigir(Request $solicitud, string $permiso, string $accion): Usuario
    {
        $usuario = $solicitud->user();
        abort_unless($usuario->tienePermiso($permiso), 403, "Su rol no tiene permiso para {$accion}.");

        return $usuario;
    }

    private function exigirEquipo(Usuario $usuario, Equipo $equipo): void
    {
        abort_unless($usuario->puedeVerCliente($equipo->edificio?->cliente_id), 403, 'Este ascensor no pertenece a su empresa.');
    }

    private function metadatos(LengthAwarePaginator $pagina): array
    {
        return [
            'pagina_actual' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'por_pagina' => $pagina->perPage(),
            'total' => $pagina->total(),
            'desde' => $pagina->firstItem(),
            'hasta' => $pagina->lastItem(),
        ];
    }

    private function equipoCorto(?Equipo $equipo): ?array
    {
        return $equipo ? [
            'id' => $equipo->id,
            'codigo' => $equipo->codigo,
            'nombre' => $equipo->nombre,
            'ubicacion' => $equipo->ubicacion,
            'edificio' => $equipo->edificio ? ['id' => $equipo->edificio->id, 'nombre' => $equipo->edificio->nombre] : null,
            'cliente' => $equipo->edificio?->cliente ? ['id' => $equipo->edificio->cliente->id, 'nombre' => $equipo->edificio->cliente->nombre] : null,
        ] : null;
    }

    // ---------- Certificación anual ----------

    public function certificacion(Certificacion $c): array
    {
        return [
            'id' => $c->id,
            'equipo_id' => $c->equipo_id,
            'organismo' => $c->organismo,
            'numero_certificado' => $c->numero_certificado,
            'norma' => $c->norma,
            'fecha_inspeccion' => $c->fecha_inspeccion->toDateString(),
            'fecha_vencimiento' => $c->fecha_vencimiento->toDateString(),
            'resultado' => $c->resultado,
            'observaciones' => $c->observaciones,
            'estado' => $c->estado(),
            'dias_para_vencer' => $c->diasParaVencer(),
            'tiene_archivo' => (bool) $c->archivo_ruta,
            'archivo_nombre' => $c->archivo_nombre,
        ];
    }

    /** Tablero: última certificación de cada ascensor y conteo por estado. */
    public function tableroCertificaciones(Request $solicitud): JsonResponse
    {
        $usuario = $this->exigir($solicitud, 'certificaciones.ver', 'consultar las certificaciones');
        $filas = $this->certificaciones->estadoPorEquipo($usuario->esCliente() ? $usuario->cliente_id : ($solicitud->integer('cliente_id') ?: null));

        return response()->json([
            'datos' => $filas->map(fn (array $f) => [
                'equipo' => $this->equipoCorto($f['equipo']),
                'estado' => $f['estado'],
                'dias_para_vencer' => $f['dias'],
                'certificacion' => $f['certificacion'] ? $this->certificacion($f['certificacion']) : null,
            ])->values(),
            'resumen' => $filas->countBy('estado'),
        ]);
    }

    public function certificacionesEquipo(Request $solicitud, Equipo $equipo): JsonResponse
    {
        $usuario = $this->exigir($solicitud, 'certificaciones.ver', 'consultar las certificaciones');
        $this->exigirEquipo($usuario, $equipo);

        return response()->json(['datos' => $equipo->certificaciones->map(fn ($c) => $this->certificacion($c))->values()]);
    }

    public function registrarCertificacion(GuardarCertificacionRequest $solicitud, Equipo $equipo): JsonResponse
    {
        $this->exigir($solicitud, 'certificaciones.gestionar', 'registrar certificaciones');
        $certificacion = $this->certificaciones->registrar($equipo, $solicitud->validated(), $solicitud->file('archivo'));

        return response()->json([
            'datos' => $this->certificacion($certificacion),
            'mensaje' => 'Certificación registrada. Vence el '.$certificacion->fecha_vencimiento->format('d/m/Y').'.',
        ], 201);
    }

    public function adjuntarCertificado(Request $solicitud, Certificacion $certificacion): JsonResponse
    {
        $this->exigir($solicitud, 'certificaciones.gestionar', 'adjuntar certificados');
        $solicitud->validate(
            ['archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']],
            ['archivo.mimes' => 'El certificado debe ser PDF, JPG o PNG.', 'archivo.max' => 'El certificado no puede pesar más de 10 MB.'],
        );

        return response()->json([
            'datos' => $this->certificacion($this->certificaciones->adjuntar($certificacion, $solicitud->file('archivo'))),
            'mensaje' => 'Certificado adjuntado.',
        ]);
    }

    public function archivoCertificado(Request $solicitud, Certificacion $certificacion): BinaryFileResponse
    {
        $usuario = $this->exigir($solicitud, 'certificaciones.ver', 'consultar las certificaciones');
        $this->exigirEquipo($usuario, $certificacion->equipo);

        return response()->download($this->certificaciones->rutaArchivo($certificacion), $certificacion->archivo_nombre ?: 'certificado.pdf');
    }

    // ---------- Emergencias ----------

    public function emergencia(Emergencia $e, bool $detalle = false): array
    {
        return [
            'id' => $e->id,
            'numero' => $e->numero,
            'equipo' => $this->equipoCorto($e->equipo),
            'tipo' => $e->tipo,
            'origen' => $e->origen,
            'personas_atrapadas' => $e->personas_atrapadas,
            'reportante_nombre' => $e->reportante_nombre,
            'reportante_telefono' => $e->reportante_telefono,
            'descripcion' => $e->descripcion,
            'estado' => $e->estado,
            'minutos_objetivo' => $e->minutos_objetivo,
            'minutos_respuesta' => $e->minutosRespuesta(),
            'dentro_objetivo' => $e->dentroDelObjetivo(),
            'tecnico' => $e->tecnico ? ['id' => $e->tecnico->id, 'nombre' => $e->tecnico->usuario?->nombre, 'telefono' => $e->tecnico->telefono] : null,
            'reportada_en' => $e->reportada_en?->toIso8601String(),
            'asignada_en' => $e->asignada_en?->toIso8601String(),
            'llegada_en' => $e->llegada_en?->toIso8601String(),
            'resuelta_en' => $e->resuelta_en?->toIso8601String(),
            'solucion' => $e->solucion,
            'orden' => $this->ordenCorta($e->orden),
            'siguientes' => Emergencia::TRANSICIONES[$e->estado] ?? [],
            'seguimiento' => $detalle ? $e->seguimientos->map(fn ($s) => [
                'estado' => $s->estado,
                'nota' => $s->nota,
                'usuario' => $s->usuario?->nombre,
                'fecha' => $s->created_at?->toIso8601String(),
            ])->values() : null,
        ];
    }

    public function emergencias(Request $solicitud): JsonResponse
    {
        $usuario = $this->exigir($solicitud, 'emergencias.ver', 'consultar las emergencias');
        $pagina = $this->emergencias->listar($solicitud->all(), $this->porPagina($solicitud), $usuario);

        return response()->json([
            'datos' => collect($pagina->items())->map(fn ($e) => $this->emergencia($e))->values(),
            'metadatos' => $this->metadatos($pagina),
        ]);
    }

    public function verEmergencia(Request $solicitud, Emergencia $emergencia): JsonResponse
    {
        $usuario = $this->exigir($solicitud, 'emergencias.ver', 'consultar las emergencias');
        $emergencia->load(['equipo.edificio.cliente', 'tecnico.usuario', 'seguimientos.usuario']);
        $this->exigirEquipo($usuario, $emergencia->equipo);

        return response()->json(['datos' => $this->emergencia($emergencia, true)]);
    }

    public function reportarEmergencia(ReportarEmergenciaRequest $solicitud): JsonResponse
    {
        $usuario = $this->exigir($solicitud, 'emergencias.reportar', 'reportar emergencias');
        $equipo = Equipo::with('edificio')->findOrFail($solicitud->integer('equipo_id'));
        $this->exigirEquipo($usuario, $equipo);
        $datos = $solicitud->validated();
        if (! empty($datos['tecnico_id']) && ! $usuario->tienePermiso('emergencias.gestionar')) {
            unset($datos['tecnico_id']);
        }
        $emergencia = $this->emergencias->reportar($equipo, $datos, $solicitud->header('X-Origen') === 'app' ? 'app' : 'panel');

        return response()->json([
            'datos' => $this->emergencia($emergencia->load(['equipo.edificio.cliente', 'tecnico.usuario', 'seguimientos.usuario']), true),
            'mensaje' => "Emergencia {$emergencia->numero} reportada. Tiempo objetivo de llegada: {$emergencia->minutos_objetivo} minutos.",
        ], 201);
    }

    public function cambiarEstadoEmergencia(Request $solicitud, Emergencia $emergencia): JsonResponse
    {
        $usuario = $solicitud->user();
        $datos = $solicitud->validate([
            'estado' => ['required', Rule::in(array_keys(Emergencia::ESTADOS))],
            'tecnico_id' => ['nullable', 'integer', Rule::exists('tecnicos', 'id')],
            'nota' => ['nullable', 'string', 'max:1000'],
            'solucion' => ['nullable', 'string', 'max:2000'],
        ]);
        $propia = $usuario->tecnico && $emergencia->tecnico_id === $usuario->tecnico->id;
        $puede = $usuario->tienePermiso('emergencias.gestionar')
            || ($usuario->tienePermiso('emergencias.atender') && in_array($datos['estado'], ['en_sitio', 'resuelta'], true)
                && ($propia || ! $emergencia->tecnico_id));
        abort_unless($puede, 403, 'Su rol no tiene permiso para cambiar el estado de esta emergencia.');

        $emergencia = $this->emergencias->cambiarEstado($emergencia, $datos['estado'], $datos);

        return response()->json([
            'datos' => $this->emergencia($emergencia->load(['equipo.edificio.cliente', 'tecnico.usuario', 'seguimientos.usuario']), true),
            'mensaje' => "Emergencia {$emergencia->numero}: ".mb_strtolower(Emergencia::ESTADOS[$emergencia->estado]).'.',
        ]);
    }

    /** Orden de trabajo de seguimiento a partir de la emergencia. */
    public function ordenDesdeEmergencia(CorrectivoDerivadoRequest $solicitud, Emergencia $emergencia): JsonResponse
    {
        $this->autorizarOrden($solicitud);
        $orden = $this->emergencias->crearOrden($emergencia, $solicitud->validated(), $this->ordenes, $this->calendario);

        return (new OrdenTrabajoResource($orden))
            ->additional(['mensaje' => "Orden {$orden->numero} creada para la emergencia {$emergencia->numero}."])
            ->response()
            ->setStatusCode(201);
    }

    // ---------- Reporte por QR (sin sesión) ----------

    public function ascensorPublico(string $codigo): JsonResponse
    {
        $equipo = Equipo::with('edificio')->where('codigo_qr', $codigo)->firstOrFail();

        return response()->json(['datos' => [
            'codigo' => $equipo->codigo,
            'nombre' => $equipo->nombre,
            'edificio' => $equipo->edificio?->nombre,
            'direccion' => $equipo->edificio?->direccion,
            'tipos' => Emergencia::TIPOS,
        ]]);
    }

    public function reportarPorQr(ReportarEmergenciaRequest $solicitud, string $codigo): JsonResponse
    {
        $equipo = Equipo::with('edificio')->where('codigo_qr', $codigo)->firstOrFail();
        $emergencia = $this->emergencias->reportar($equipo, $solicitud->validated(), 'qr');

        return response()->json([
            'datos' => ['numero' => $emergencia->numero, 'minutos_objetivo' => $emergencia->minutos_objetivo],
            'mensaje' => "Recibimos su reporte {$emergencia->numero}. Un técnico va en camino; el tiempo objetivo de llegada es de {$emergencia->minutos_objetivo} minutos.",
        ], 201);
    }

    public function codigoQr(Request $solicitud, Equipo $equipo): JsonResponse
    {
        $usuario = $this->exigir($solicitud, 'equipos.ver', 'consultar ascensores');
        $this->exigirEquipo($usuario, $equipo);

        return response()->json(['datos' => [
            'codigo_qr' => $equipo->codigo_qr,
            'url' => rtrim(config('sigma.url_frontend'), '/').'/reportar/'.$equipo->codigo_qr,
        ]]);
    }

    // ---------- Cotizaciones ----------

    public function cotizacion(Cotizacion $c, bool $detalle = false): array
    {
        return [
            'id' => $c->id,
            'numero' => $c->numero,
            'titulo' => $c->titulo,
            'descripcion' => $c->descripcion,
            'equipo' => $this->equipoCorto($c->equipo),
            'cliente' => $c->cliente ? ['id' => $c->cliente->id, 'nombre' => $c->cliente->nombre] : null,
            'orden_id' => $c->orden_id,
            'orden' => $this->ordenCorta($c->orden),
            'resultado_origen_id' => $c->resultado_origen_id,
            'subtotal' => (float) $c->subtotal,
            'iva' => (float) $c->iva,
            'total' => (float) $c->total,
            'fecha_emision' => $c->fecha_emision->toDateString(),
            'vigente_hasta' => $c->vigente_hasta->toDateString(),
            'estado' => $c->estadoActual(),
            'respondida_en' => $c->respondida_en?->toIso8601String(),
            'respondida_por' => $c->respondidaPor?->nombre,
            'motivo_rechazo' => $c->motivo_rechazo,
            'items' => $detalle ? $c->items->map(fn ($i) => [
                'descripcion' => $i->descripcion,
                'cantidad' => (float) $i->cantidad,
                'valor_unitario' => (float) $i->valor_unitario,
                'valor_total' => (float) $i->valor_total,
            ])->values() : null,
        ];
    }

    public function cotizaciones(Request $solicitud): JsonResponse
    {
        $usuario = $this->exigir($solicitud, 'cotizaciones.ver', 'consultar las cotizaciones');
        $pagina = $this->cotizaciones->listar($solicitud->all(), $this->porPagina($solicitud), $usuario);

        return response()->json([
            'datos' => collect($pagina->items())->map(fn ($c) => $this->cotizacion($c))->values(),
            'metadatos' => $this->metadatos($pagina),
        ]);
    }

    public function verCotizacion(Request $solicitud, Cotizacion $cotizacion): JsonResponse
    {
        $usuario = $this->exigir($solicitud, 'cotizaciones.ver', 'consultar las cotizaciones');
        abort_if($usuario->esCliente() && $cotizacion->cliente_id !== $usuario->cliente_id, 403, 'Esta cotización no pertenece a su empresa.');

        return response()->json(['datos' => $this->cotizacion($cotizacion->load(['equipo.edificio.cliente', 'cliente', 'items', 'respondidaPor']), true)]);
    }

    public function crearCotizacion(GuardarCotizacionRequest $solicitud): JsonResponse
    {
        $this->exigir($solicitud, 'cotizaciones.crear', 'crear cotizaciones');
        $cotizacion = $this->cotizaciones->crear($solicitud->validated());

        return response()->json([
            'datos' => $this->cotizacion($cotizacion->load(['equipo.edificio.cliente', 'cliente', 'items']), true),
            'mensaje' => "Cotización {$cotizacion->numero} enviada al cliente.",
        ], 201);
    }

    public function responderCotizacion(Request $solicitud, Cotizacion $cotizacion): JsonResponse
    {
        $usuario = $this->exigir($solicitud, 'cotizaciones.responder', 'responder cotizaciones');
        abort_if($usuario->esCliente() && $cotizacion->cliente_id !== $usuario->cliente_id, 403, 'Esta cotización no pertenece a su empresa.');
        $datos = $solicitud->validate([
            'decision' => ['required', Rule::in(['aprobada', 'rechazada'])],
            'motivo' => ['nullable', 'string', 'max:1000'],
        ]);
        $cotizacion = $this->cotizaciones->responder($cotizacion, $datos['decision'], $datos['motivo'] ?? null);

        return response()->json([
            'datos' => $this->cotizacion($cotizacion->load(['equipo.edificio.cliente', 'cliente', 'items', 'respondidaPor']), true),
            'mensaje' => $datos['decision'] === 'aprobada'
                ? "Cotización {$cotizacion->numero} aprobada. Le avisaremos cuando se programe el trabajo."
                : "Cotización {$cotizacion->numero} rechazada. Su coordinador revisará el motivo.",
        ]);
    }

    /** Orden de trabajo que ejecuta la cotización aprobada. */
    public function ordenDesdeCotizacion(CorrectivoDerivadoRequest $solicitud, Cotizacion $cotizacion): JsonResponse
    {
        $this->autorizarOrden($solicitud);
        $orden = $this->cotizaciones->crearOrden($cotizacion, $solicitud->validated(), $this->ordenes, $this->calendario);

        return (new OrdenTrabajoResource($orden))
            ->additional(['mensaje' => "Orden {$orden->numero} creada para la cotización {$cotizacion->numero}."])
            ->response()
            ->setStatusCode(201);
    }

    // ---------- Indicadores y documentos ----------

    public function indicadores(Request $solicitud): JsonResponse
    {
        return response()->json(['datos' => $this->indicadores->calcular($this->filtrosIndicadores($solicitud))]);
    }

    /** Indicadores en PDF para el comité o la administración del edificio. */
    public function indicadoresPdf(Request $solicitud): Response
    {
        $filtros = $this->filtrosIndicadores($solicitud);
        $pdf = $this->indicadores->pdf($filtros);
        $periodo = $this->indicadores->calcular($filtros)['periodo'];

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"SIGMA_Indicadores_{$periodo['desde']}_{$periodo['hasta']}.pdf\"",
        ]);
    }

    private function filtrosIndicadores(Request $solicitud): array
    {
        $usuario = $this->exigir($solicitud, 'indicadores.ver', 'consultar los indicadores del servicio');
        $filtros = $solicitud->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'cliente_id' => ['nullable', 'integer'],
            'equipo_id' => ['nullable', 'integer'],
        ]);
        if ($usuario->esCliente()) {
            $filtros['cliente_id'] = $usuario->cliente_id;
        }
        if (! empty($filtros['equipo_id'])) {
            $this->exigirEquipo($usuario, Equipo::with('edificio')->findOrFail($filtros['equipo_id']));
        }

        return $filtros;
    }

    /** Documentos del ascensor: certificados anuales e informes técnicos firmados. */
    public function documentosEquipo(Request $solicitud, Equipo $equipo): JsonResponse
    {
        $usuario = $this->exigir($solicitud, 'equipos.historial.ver', 'consultar el historial del ascensor');
        $this->exigirEquipo($usuario, $equipo);

        $certificados = $equipo->certificaciones->filter(fn ($c) => $c->archivo_ruta)->map(fn ($c) => [
            'tipo' => 'certificado',
            'titulo' => 'Certificado de revisión anual'.($c->numero_certificado ? " {$c->numero_certificado}" : ''),
            'fecha' => $c->fecha_inspeccion->toDateString(),
            'detalle' => $c->organismo,
            'enlace' => "/certificaciones/{$c->id}/archivo",
        ]);

        $informes = InformeOrden::query()
            ->whereHas('orden', fn ($o) => $o->where('equipo_id', $equipo->id))
            ->with('orden')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($i) => [
                'tipo' => 'informe',
                'titulo' => "Informe técnico {$i->orden?->numero}".($i->version > 1 ? " (versión {$i->version})" : ''),
                'fecha' => $i->created_at?->toDateString(),
                'detalle' => $i->orden ? ucfirst($i->orden->servicio) : null,
                'enlace' => "/ordenes/{$i->orden_id}/informes/{$i->id}/pdf",
            ]);

        return response()->json(['datos' => $certificados->concat($informes)->sortByDesc('fecha')->values()]);
    }
}
