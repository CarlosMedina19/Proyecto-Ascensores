<?php

namespace App\Services;

use App\Models\Elevator;
use App\Models\Equipment;
use App\Models\EquipmentHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EquipmentService
{
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Equipment::with(['building.client', 'elevator']);

        if (! empty($filters['building_id'])) {
            $query->where('edificio_id', $filters['building_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('tipo', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('estado', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('codigo', $like, "%{$search}%")
                    ->orWhere('marca', $like, "%{$search}%")
                    ->orWhere('modelo', $like, "%{$search}%")
                    ->orWhere('numero_serie', $like, "%{$search}%")
                    ->orWhere('ubicacion', $like, "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function create(array $data): Equipment
    {
        return DB::transaction(function () use ($data) {
            $equipmentData = collect($data)->except(['elevator'])->toArray();
            $equipment = Equipment::create($equipmentData);

            if (! empty($data['elevator'])) {
                $elevatorData = array_merge($data['elevator'], ['equipo_id' => $equipment->id]);
                Elevator::create($elevatorData);
            }

            $equipment->history()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'alta_equipo',
                'descripcion' => "Ascensor registrado con código {$equipment->codigo}.",
            ]);

            AuditService::log('created', $equipment, $equipment->toArray());

            return $equipment->load(['building.client', 'elevator']);
        });
    }

    public function update(Equipment $equipment, array $data): Equipment
    {
        return DB::transaction(function () use ($equipment, $data) {
            $equipmentData = collect($data)->except(['elevator'])->toArray();
            $oldStatus = $equipment->estado;
            $equipment->update($equipmentData);

            if (isset($data['elevator'])) {
                $equipment->elevator()->updateOrCreate(
                    ['equipo_id' => $equipment->id],
                    $data['elevator']
                );
            }

            if ($equipment->estado !== $oldStatus) {
                $equipment->history()->create([
                    'usuario_id' => Auth::id(),
                    'evento' => 'cambio_estado',
                    'descripcion' => "Estado actualizado de {$oldStatus} a {$equipment->estado}.",
                ]);
            }

            AuditService::log('updated', $equipment, $equipment->getChanges());

            return $equipment->fresh(['building.client', 'elevator', 'history']);
        });
    }

    public function delete(Equipment $equipment): void
    {
        DB::transaction(function () use ($equipment) {
            $equipment->history()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'baja_equipo',
                'descripcion' => 'Equipo dado de baja del sistema.',
            ]);
            $equipment->delete();
            AuditService::log('deleted', $equipment);
        });
    }

    public function addHistory(Equipment $equipment, array $data): EquipmentHistory
    {
        $history = $equipment->history()->create([
            'usuario_id' => Auth::id(),
            'evento' => $data['evento'],
            'descripcion' => $data['descripcion'] ?? null,
        ]);

        AuditService::log('created', $history, $history->toArray());

        return $history->load('user');
    }
}
