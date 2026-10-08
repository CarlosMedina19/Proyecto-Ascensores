<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Listar usuarios con filtros y paginación (RF-003).
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with('role');

        if (!empty($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', $like, "%{$search}%")
                  ->orWhere('email', $like, "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Crear usuario con contraseña hasheada y auditoría (RF-003).
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }
            $data['is_active'] = $data['is_active'] ?? true;

            $user = User::create($data);
            AuditService::log('created', $user, collect($user->toArray())->except('password')->toArray());
            return $user->load('role');
        });
    }

    /**
     * Actualizar usuario con auditoría (RF-003).
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $user->update($data);
            AuditService::log('updated', $user, collect($user->getChanges())->except('password')->toArray());
            return $user->fresh(['role']);
        });
    }

    /**
     * Alternar estado activo/inactivo (RF-003).
     */
    public function toggleStatus(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user->update(['is_active' => !$user->is_active]);
            AuditService::log('updated', $user, ['is_active' => $user->is_active]);
            return $user->fresh(['role']);
        });
    }
}
