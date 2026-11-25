<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipo;
use App\Models\VisitaTecnica;
use App\Services\VisitaTecnicaService;
use App\Support\Archivos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Planilla de asistencia técnica de cada visita al ascensor y su PDF. */
class VisitaTecnicaController extends Controller
{
    public function __construct(private readonly VisitaTecnicaService $visitas) {}

    /**
     * Planillas recientes que el usuario puede ver (pestaña Visitas de la app): el técnico, las suyas; el cliente,
     * las de sus ascensores; los demás roles con acceso a ascensores, todas.
     */
    public function recientes(Request $solicitud): JsonResponse
    {
        $usuario = $solicitud->user();
        abort_unless($usuario->tienePermiso('equipos.ver'), 403, 'Su rol no puede consultar las visitas.');

        $visitas = VisitaTecnica::with(['tecnico', 'equipo.edificio'])
            ->when($usuario->esTecnico(), fn ($q) => $q->where('tecnico_usuario_id', $usuario->id))
            ->when($usuario->esCliente(), fn ($q) => $q->whereHas('equipo.edificio', fn ($e) => $e->where('cliente_id', $usuario->cliente_id)))
            ->latest('salida_en')->limit(30)->get();

        return response()->json(['datos' => $visitas->map(fn (VisitaTecnica $v) => $v->resumen() + [
            'equipo' => ['id' => $v->equipo_id, 'codigo' => $v->equipo?->codigo, 'edificio' => $v->equipo?->edificio?->nombre],
        ])->values()]);
    }

    public function index(Equipo $equipo): JsonResponse
    {
        $this->authorize('view', $equipo);

        return response()->json(['datos' => VisitaTecnica::with('tecnico')->where('equipo_id', $equipo->id)
            ->latest('salida_en')->limit(50)->get()->map->resumen()->values()]);
    }

    public function store(Request $solicitud, Equipo $equipo): JsonResponse
    {
        $this->authorize('agregarHistorial', $equipo);
        $datos = $solicitud->validate([
            'tipo_servicio' => ['required', Rule::in(array_keys(VisitaTecnica::TIPOS))],
            'entrada_en' => ['required', 'date', 'before_or_equal:now'],
            'salida_en' => ['nullable', 'date', 'before_or_equal:now'],
            'resultados' => ['required', 'array', 'min:1', 'max:300'],
            'resultados.*.item_id' => ['required', 'integer'],
            'resultados.*.ins' => ['nullable', 'boolean'],
            'resultados.*.estado' => ['nullable', 'in:B,R,M'],
            'resultados.*.na' => ['nullable', 'boolean'],
            'resultados.*.observacion' => ['nullable', 'string', 'max:500'],
            'observaciones' => ['nullable', 'string', 'max:3000'],
            'repuestos' => ['nullable', 'array', 'max:50'],
            'repuestos.*.nombre' => ['required', 'string', 'max:120'],
            'repuestos.*.cantidad' => ['required', 'integer', 'min:1', 'max:999'],
            'recibe_nombre' => ['required', 'string', 'max:150'],
            'recibe_documento' => ['required', 'string', 'max:30'],
            'tecnico_documento' => ['nullable', 'string', 'max:30'],
            'firma_tecnico' => ['required', 'string', 'max:800000'],
            'firma_recibe' => ['required', 'string', 'max:800000'],
        ], [], [
            'recibe_nombre' => 'nombre de quien recibe',
            'recibe_documento' => 'C.C. de quien recibe',
            'firma_tecnico' => 'firma del técnico',
            'firma_recibe' => 'firma de quien recibe',
            'entrada_en' => 'hora de entrada',
        ]);
        $visita = $this->visitas->registrar($equipo, $solicitud->user(), $datos);

        return response()->json([
            'datos' => $visita->load('tecnico')->resumen(),
            'mensaje' => "Planilla N.º {$visita->numero} registrada. El PDF quedó en la ficha del ascensor.",
        ], 201);
    }

    public function pdf(VisitaTecnica $visita): StreamedResponse
    {
        $this->authorize('view', $visita->equipo);

        return Archivos::responder((string) $visita->pdf_ruta, "Planilla-{$visita->numero}.pdf", 'application/pdf');
    }
}
