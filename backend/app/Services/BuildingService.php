<?php

namespace App\Services;

use App\Models\Building;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BuildingService
{
    /**
     * Listar edificios con filtros y paginación (RF-006).
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Building::with(['client'])->withCount('equipment');

        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (!empty($filters['city'])) {
            $query->where('city', $like, "%{$filters['city']}%");
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', $like, "%{$search}%")
                  ->orWhere('address', $like, "%{$search}%")
                  ->orWhere('city', $like, "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Crear edificio con auditoría.
     */
    public function create(array $data): Building
    {
        return DB::transaction(function () use ($data) {
            $building = Building::create($data);
            AuditService::log('created', $building, $building->toArray());
            return $building->load('client');
        });
    }

    /**
     * Actualizar edificio con auditoría.
     */
    public function update(Building $building, array $data): Building
    {
        return DB::transaction(function () use ($building, $data) {
            $building->update($data);
            AuditService::log('updated', $building, $building->getChanges());
            return $building->fresh(['client']);
        });
    }

    /**
     * Eliminar edificio (soft delete) con auditoría.
     */
    public function delete(Building $building): void
    {
        DB::transaction(function () use ($building) {
            $building->delete();
            AuditService::log('deleted', $building);
        });
    }
}
