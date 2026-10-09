<?php

namespace App\Services;

use App\Models\Building;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BuildingService
{
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Building::with(['client'])->withCount('equipment');

        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        if (! empty($filters['client_id'])) {
            $query->where('cliente_id', $filters['client_id']);
        }

        if (! empty($filters['city'])) {
            $query->where('ciudad', $like, "%{$filters['city']}%");
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search, $like) {
                $q->where('nombre', $like, "%{$search}%")
                    ->orWhere('direccion', $like, "%{$search}%")
                    ->orWhere('ciudad', $like, "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function create(array $data): Building
    {
        return DB::transaction(function () use ($data) {
            $building = Building::create($data);
            AuditService::log('created', $building, $building->toArray());

            return $building->load('client');
        });
    }

    public function update(Building $building, array $data): Building
    {
        return DB::transaction(function () use ($building, $data) {
            $building->update($data);
            AuditService::log('updated', $building, $building->getChanges());

            return $building->fresh(['client']);
        });
    }

    public function delete(Building $building): void
    {
        DB::transaction(function () use ($building) {
            $building->delete();
            AuditService::log('deleted', $building);
        });
    }
}
