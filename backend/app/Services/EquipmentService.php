<?php

namespace App\Services;

use App\Models\ElectricDoor;
use App\Models\Elevator;
use App\Models\Equipment;
use App\Models\EquipmentHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EquipmentService
{
    /**
     * Listar equipos con filtros, paginación y subtipos (RF-007).
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Equipment::with(['building.client', 'elevator', 'electricDoor']);

        if (!empty($filters['building_id'])) {
            $query->where('building_id', $filters['building_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('code', $like, "%{$search}%")
                  ->orWhere('brand', $like, "%{$search}%")
                  ->orWhere('model', $like, "%{$search}%")
                  ->orWhere('serial_number', $like, "%{$search}%")
                  ->orWhere('location', $like, "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Crear equipo con su detalle técnico (Ascensor o Puerta) dentro de una transacción (RF-007, RF-008, RF-009).
     */
    public function create(array $data): Equipment
    {
        return DB::transaction(function () use ($data) {
            $equipmentData = collect($data)->except(['elevator', 'electric_door'])->toArray();
            $equipment = Equipment::create($equipmentData);

            if ($equipment->type === 'elevator' && !empty($data['elevator'])) {
                $elevatorData = array_merge($data['elevator'], ['equipment_id' => $equipment->id]);
                Elevator::create($elevatorData);
            } elseif ($equipment->type === 'electric_door' && !empty($data['electric_door'])) {
                $doorData = array_merge($data['electric_door'], ['equipment_id' => $equipment->id]);
                ElectricDoor::create($doorData);
            }

            // Registrar en historial inicial (RF-010)
            $equipment->history()->create([
                'user_id' => Auth::id(),
                'event' => 'alta_equipo',
                'description' => "Equipo creado con código {$equipment->code} de tipo {$equipment->type}.",
            ]);

            AuditService::log('created', $equipment, $equipment->toArray());

            return $equipment->load(['building.client', 'elevator', 'electricDoor']);
        });
    }

    /**
     * Actualizar equipo y subtipo técnico (RF-007, RF-008, RF-009).
     */
    public function update(Equipment $equipment, array $data): Equipment
    {
        return DB::transaction(function () use ($equipment, $data) {
            $equipmentData = collect($data)->except(['elevator', 'electric_door'])->toArray();
            $oldStatus = $equipment->status;
            $equipment->update($equipmentData);

            if ($equipment->type === 'elevator' && isset($data['elevator'])) {
                $equipment->elevator()->updateOrCreate(
                    ['equipment_id' => $equipment->id],
                    $data['elevator']
                );
            } elseif ($equipment->type === 'electric_door' && isset($data['electric_door'])) {
                $equipment->electricDoor()->updateOrCreate(
                    ['equipment_id' => $equipment->id],
                    $data['electric_door']
                );
            }

            // Si cambió el estado, registrar evento en el historial (RF-010)
            if ($equipment->status !== $oldStatus) {
                $equipment->history()->create([
                    'user_id' => Auth::id(),
                    'event' => 'cambio_estado',
                    'description' => "Estado actualizado de {$oldStatus} a {$equipment->status}.",
                ]);
            }

            AuditService::log('updated', $equipment, $equipment->getChanges());

            return $equipment->fresh(['building.client', 'elevator', 'electricDoor', 'history']);
        });
    }

    /**
     * Eliminar equipo con auditoría.
     */
    public function delete(Equipment $equipment): void
    {
        DB::transaction(function () use ($equipment) {
            $equipment->history()->create([
                'user_id' => Auth::id(),
                'event' => 'baja_equipo',
                'description' => "Equipo dado de baja del sistema.",
            ]);
            $equipment->delete();
            AuditService::log('deleted', $equipment);
        });
    }

    /**
     * Registrar un nuevo evento en la hoja de vida / historial del equipo (RF-010).
     */
    public function addHistory(Equipment $equipment, array $data): EquipmentHistory
    {
        $history = $equipment->history()->create([
            'user_id' => Auth::id(),
            'event' => $data['event'],
            'description' => $data['description'] ?? null,
        ]);

        AuditService::log('created', $history, $history->toArray());

        return $history->load('user');
    }
}
