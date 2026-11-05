<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tipo de ascensor según su uso (catálogo configurable: pasajeros, carga, camillero…).
 */
class TipoAscensor extends Model
{
    protected $table = 'tipos_ascensor';

    protected $fillable = ['clave', 'nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /** Nombres por clave, para no consultar la tabla por cada ascensor de un listado. */
    private static ?array $nombres = null;

    protected static function booted(): void
    {
        static::saved(fn () => self::$nombres = null);
        static::deleted(fn () => self::$nombres = null);

        static::creating(function (TipoAscensor $tipo) {
            if (blank($tipo->clave)) {
                $base = Str::slug($tipo->nombre, '_') ?: 'tipo';
                $clave = $base;
                for ($n = 2; static::where('clave', $clave)->exists(); $n++) {
                    $clave = "{$base}_{$n}";
                }
                $tipo->clave = $clave;
            }
        });
    }

    /** @return array<string, string> clave => nombre */
    public static function opciones(bool $soloActivos = false): array
    {
        return static::query()->when($soloActivos, fn ($c) => $c->where('activo', true))->orderBy('nombre')->pluck('nombre', 'clave')->all();
    }

    public static function nombre(?string $clave): ?string
    {
        if ($clave === null) {
            return null;
        }
        self::$nombres ??= static::pluck('nombre', 'clave')->all();

        return self::$nombres[$clave] ?? $clave;
    }
}
