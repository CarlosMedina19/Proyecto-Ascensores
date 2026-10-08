<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ClientService
{
    /**
     * Listar clientes con filtros y paginación (RF-005).
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Client::with(['contacts'])->withCount('buildings');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', $like, "%{$search}%")
                  ->orWhere('nit', $like, "%{$search}%")
                  ->orWhere('document_number', $like, "%{$search}%")
                  ->orWhere('email', $like, "%{$search}%");
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Crear cliente con auditoría.
     */
    public function create(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            $client = Client::create($data);
            AuditService::log('created', $client, $client->toArray());
            return $client->load('contacts');
        });
    }

    /**
     * Actualizar cliente con auditoría.
     */
    public function update(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($client, $data) {
            $client->update($data);
            AuditService::log('updated', $client, $client->getChanges());
            return $client->fresh(['contacts']);
        });
    }

    /**
     * Eliminar cliente (soft delete) con auditoría.
     */
    public function delete(Client $client): void
    {
        DB::transaction(function () use ($client) {
            $client->delete();
            AuditService::log('deleted', $client);
        });
    }

    /**
     * Agregar contacto a un cliente.
     */
    public function addContact(Client $client, array $data): ClientContact
    {
        $contact = $client->contacts()->create($data);
        AuditService::log('created', $contact, $contact->toArray());
        return $contact;
    }
}
