<?php

namespace App\Models;

use App\Models\Concerns\TieneUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RF-010: plan del catálogo, con un precio por periodicidad (RF-012).
 */
class PlanMantenimiento extends Model
{
    use SoftDeletes, TieneUuid;

    protected $table = 'planes_mantenimiento';

    protected $fillable = ['nombre', 'descripcion', 'servicios_incluidos', 'activo'];

    protected function casts(): array
    {
        return ['servicios_incluidos' => 'array', 'activo' => 'boolean'];
    }

    public function precios(): HasMany
    {
        return $this->hasMany(PrecioPlan::class, 'plan_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionPlan::class, 'plan_id');
    }

    public function historialPrecios(): HasMany
    {
        return $this->hasMany(HistorialPrecio::class, 'plan_id');
    }

    public function precioPara(int $periodicidadId): ?string
    {
        return $this->precios()->where('periodicidad_id', $periodicidadId)->value('precio');
    }
}
