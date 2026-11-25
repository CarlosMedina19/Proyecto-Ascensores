<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * RF-031 a RF-036: componente del ascensor que se revisa en la planilla.
 */
class ChecklistItem extends Model
{
    protected $table = 'checklist_items';

    /**
     * Zonas del esquema del ascensor (claves fijas de las secciones base). Los nombres visibles y el orden
     * de las secciones están en checklist_secciones y los edita el administrador.
     */
    public const SECCIONES = [
        'cuarto_maquinas' => 'Cuarto de máquinas',
        'cabina' => 'Cabina',
        'contrapeso' => 'Contrapeso',
        'puertas' => 'Puertas de cabina y de piso',
        'hueco' => 'Hueco y recorrido',
        'foso' => 'Foso',
    ];

    protected $fillable = ['seccion', 'codigo', 'nombre', 'orden', 'critico', 'activo'];

    protected function casts(): array
    {
        return ['critico' => 'boolean', 'activo' => 'boolean', 'orden' => 'integer'];
    }

    /** Actividades que hoy se diligencian: activas y de una sección en uso (no quitada ni desactivada). */
    public function scopeVigentes(Builder $consulta): Builder
    {
        return $consulta->where('activo', true)->whereIn('seccion', ChecklistSeccion::enUso()->select('clave'));
    }

    public function nombreSeccion(): string
    {
        return ChecklistSeccion::nombre($this->seccion);
    }
}
