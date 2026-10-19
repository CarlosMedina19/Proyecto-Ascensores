<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RF-031 a RF-036: componente del ascensor que se revisa en la planilla.
 */
class ChecklistItem extends Model
{
    protected $table = 'checklist_items';

    /** Secciones en el orden en que se recorren y su nombre visible. */
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

    public function nombreSeccion(): string
    {
        return self::SECCIONES[$this->seccion] ?? $this->seccion;
    }
}
