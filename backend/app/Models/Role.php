<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = ['nombre', 'descripcion'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'rol_permisos', 'rol_id', 'permiso_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'rol_id');
    }

    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('permisos.nombre', $permissionName)->exists();
    }
}
