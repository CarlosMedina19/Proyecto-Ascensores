<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tecnico\GuardarTecnicoRequest;
use App\Http\Requests\Tecnico\PerfilTecnicoRequest;
use App\Http\Resources\TecnicoResource;
use App\Models\Especialidad;
use App\Models\Tecnico;
use App\Models\Usuario;
use App\Services\TecnicoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * RF-022: técnicos, especialidades y disponibilidad.
 */
class TecnicoController extends Controller
{
    public function __construct(
        protected TecnicoService $servicioTecnicos
    ) {}

    public function index(Request $solicitud): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tecnico::class);

        return TecnicoResource::collection(
            $this->servicioTecnicos->listar($solicitud->all(), $this->porPagina($solicitud))
        );
    }

    public function store(GuardarTecnicoRequest $solicitud): JsonResponse
    {
        $this->authorize('create', Tecnico::class);
        $usuario = $this->servicioTecnicos->crear($solicitud->validated());

        return (new TecnicoResource($usuario))->response()->setStatusCode(201);
    }

    public function show(Usuario $usuario): TecnicoResource
    {
        $this->exigirTecnico($usuario);
        $this->authorize('view', [Tecnico::class, $usuario]);
        $usuario->load('tecnico.especialidades');
        if ($usuario->tecnico) {
            $usuario->ordenes_activas = $this->servicioTecnicos->ordenesActivas($usuario->tecnico);
        }

        return new TecnicoResource($usuario);
    }

    public function guardarPerfil(PerfilTecnicoRequest $solicitud, Usuario $usuario): TecnicoResource
    {
        $this->exigirTecnico($usuario);
        $this->authorize('update', Tecnico::class);

        return new TecnicoResource($this->servicioTecnicos->guardarPerfil($usuario, $solicitud->validated()));
    }

    public function cambiarEstado(Request $solicitud, Usuario $usuario): TecnicoResource
    {
        $this->exigirTecnico($usuario);
        $this->authorize('update', Tecnico::class);
        $datos = $solicitud->validate(['activo' => ['required', 'boolean']]);

        return new TecnicoResource($this->servicioTecnicos->cambiarEstado($usuario, (bool) $datos['activo']));
    }

    /** RF-022/RF-023: quién puede tomar una orden en un horario (para el selector de técnicos). */
    public function disponibilidad(Request $solicitud): JsonResponse
    {
        $this->authorize('viewAny', Tecnico::class);
        $datos = $solicitud->validate([
            'fecha' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
            'duracion' => ['nullable', 'integer', 'min:15', 'max:720'],
            'orden_id' => ['nullable', 'integer'],
        ]);

        $lista = $this->servicioTecnicos->disponibilidad(
            CarbonImmutable::parse("{$datos['fecha']} {$datos['hora']}"),
            (int) ($datos['duracion'] ?? 90),
            isset($datos['orden_id']) ? (int) $datos['orden_id'] : null,
        );

        return response()->json([
            'datos' => $lista->map(fn (array $fila) => [
                'id' => $fila['tecnico']->id,
                'usuario_id' => $fila['tecnico']->usuario_id,
                'nombre' => $fila['tecnico']->usuario?->nombre,
                'especialidades' => $fila['tecnico']->especialidades->pluck('nombre')->values(),
                'asignable' => $fila['asignable'],
                'motivo' => $fila['motivo'],
                'conflictos' => $fila['conflictos'],
            ]),
        ]);
    }

    public function especialidades(): JsonResponse
    {
        return response()->json([
            'datos' => Especialidad::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    private function exigirTecnico(Usuario $usuario): void
    {
        abort_unless($usuario->esTecnico(), 404, 'El usuario no es un técnico.');
    }
}
