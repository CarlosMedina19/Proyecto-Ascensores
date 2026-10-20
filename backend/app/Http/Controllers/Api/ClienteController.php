<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\ActualizarClienteRequest;
use App\Http\Requests\Cliente\GuardarClienteRequest;
use App\Http\Requests\Cliente\GuardarContactoClienteRequest;
use App\Http\Resources\ClienteResource;
use App\Http\Resources\ContactoClienteResource;
use App\Models\Cliente;
use App\Models\Edificio;
use App\Services\ClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClienteController extends Controller
{
    public function __construct(
        protected ClienteService $servicioCliente
    ) {}

    public function index(Request $solicitud): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Cliente::class);
        $clientes = $this->servicioCliente->listar($solicitud->all(), $this->porPagina($solicitud));

        return ClienteResource::collection($clientes);
    }

    public function store(GuardarClienteRequest $solicitud): JsonResponse
    {
        $this->authorize('create', Cliente::class);
        $cliente = $this->servicioCliente->crear($solicitud->validated());

        return (new ClienteResource($cliente))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Cliente $cliente): ClienteResource
    {
        $this->authorize('view', $cliente);

        return new ClienteResource($cliente->load([
            'contactos',
            'edificios' => fn ($consulta) => $consulta->withCount('equipos')->orderBy('nombre'),
        ]));
    }

    /**
     * Árbol del cliente: tipos de edificio (conjunto, institucional, residencial) que tiene,
     * con sus edificios y los ascensores de cada uno.
     */
    public function estructura(Cliente $cliente): JsonResponse
    {
        $this->authorize('view', $cliente);

        $edificios = $cliente->edificios()
            ->with(['equipos' => fn ($consulta) => $consulta->orderBy('codigo')])
            ->orderBy('nombre')
            ->get();

        $grupos = collect(Edificio::TIPOS)
            ->map(fn ($nombre, $tipo) => [
                'tipo' => $tipo,
                'nombre' => $nombre,
                'edificios' => $edificios->where('tipo', $tipo)->values()->map(fn (Edificio $edificio) => [
                    'id' => $edificio->id,
                    'nombre' => $edificio->nombre,
                    'direccion' => $edificio->direccion,
                    'ciudad' => $edificio->ciudad,
                    'activo' => (bool) $edificio->activo,
                    'equipos' => $edificio->equipos->map(fn ($equipo) => [
                        'id' => $equipo->id,
                        'codigo' => $equipo->codigo,
                        'marca' => $equipo->marca,
                        'modelo' => $equipo->modelo,
                        'estado' => $equipo->estado,
                    ])->values(),
                ]),
            ])
            ->filter(fn ($grupo) => $grupo['edificios']->isNotEmpty())
            ->values();

        return response()->json(['datos' => $grupos]);
    }

    public function update(ActualizarClienteRequest $solicitud, Cliente $cliente): ClienteResource
    {
        $this->authorize('update', $cliente);
        $actualizado = $this->servicioCliente->actualizar($cliente, $solicitud->validated());

        return new ClienteResource($actualizado);
    }

    public function destroy(Cliente $cliente): JsonResponse
    {
        $this->authorize('delete', $cliente);
        $this->servicioCliente->eliminar($cliente);

        return response()->json([
            'exito' => true,
            'mensaje' => 'Cliente eliminado correctamente',
        ]);
    }

    public function agregarContacto(GuardarContactoClienteRequest $solicitud, Cliente $cliente): JsonResponse
    {
        $this->authorize('agregarContacto', $cliente);
        $contacto = $this->servicioCliente->agregarContacto($cliente, $solicitud->validated());

        return (new ContactoClienteResource($contacto))
            ->response()
            ->setStatusCode(201);
    }
}
