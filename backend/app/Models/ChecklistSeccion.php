<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sección de la planilla de mantenimiento (editable por el administrador).
 * activo = no se ha quitado de la planilla; habilitada = el administrador no la desactivó con el botón.
 */
class ChecklistSeccion extends Model
{
    protected $table = 'checklist_secciones';

    protected $fillable = ['clave', 'nombre', 'orden', 'activo', 'habilitada'];

    /** Nombres por clave, cacheados durante la solicitud. */
    private static ?array $nombres = null;

    protected function casts(): array
    {
        return ['orden' => 'integer', 'activo' => 'boolean', 'habilitada' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::$nombres = null);
        static::deleted(fn () => self::$nombres = null);
    }

    /** Secciones que hoy se diligencian: no quitadas y no desactivadas. */
    public function scopeEnUso(Builder $consulta): Builder
    {
        return $consulta->where('activo', true)->where('habilitada', true);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class, 'seccion', 'clave');
    }

    /**
     * Secciones en uso (no quitadas ni desactivadas), en orden: clave => nombre.
     *
     * @return array<string, string>
     */
    public static function mapa(): array
    {
        return self::$nombres ??= self::enUso()->orderBy('orden')->orderBy('id')->pluck('nombre', 'clave')->all();
    }

    public static function nombre(string $clave): string
    {
        return self::mapa()[$clave] ?? (self::where('clave', $clave)->value('nombre') ?? ChecklistItem::SECCIONES[$clave] ?? $clave);
    }
}
