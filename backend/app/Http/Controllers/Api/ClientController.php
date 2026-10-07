<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientContactRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientContactResource;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    public function __construct(
        protected ClientService $clientService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $clients = $this->clientService->list($request->all(), (int) $request->input('per_page', 15));
        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = $this->clientService->create($request->validated());
        return (new ClientResource($client))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        return new ClientResource($client->load(['contacts', 'buildings.equipment']));
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $updated = $this->clientService->update($client, $request->validated());
        return new ClientResource($updated);
    }

    public function destroy(Client $client): JsonResponse
    {
        $this->clientService->delete($client);
        return response()->json([
            'success' => true,
            'message' => 'Cliente eliminado correctamente',
        ]);
    }

    public function addContact(StoreClientContactRequest $request, Client $client): JsonResponse
    {
        $contact = $this->clientService->addContact($client, $request->validated());
        return (new ClientContactResource($contact))
            ->response()
            ->setStatusCode(201);
    }
}
