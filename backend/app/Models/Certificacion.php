<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Revisión general anual del ascensor por un organismo de inspección acreditado por ONAC.
 * En Bogotá la exige el Acuerdo 470 de 2011 con la NTC 5926-1; en otras ciudades se registra la norma local.
 */
class Certificacion extends Model
{
    protected $table = 'certificaciones';

    public const RESULTADOS = ['conforme' => 'Conforme', 'condicionado' => 'Conforme con observaciones', 'no_conforme' => 'No conforme'];

    protected $fillable = [
        'equipo_id', 'organismo', 'numero_certificado', 'norma', 'fecha_inspeccion', 'fecha_vencimiento',
        'resultado', 'observaciones', 'archivo_ruta', 'archivo_nombre', 'registrado_por',
    ];

    protected function casts(): array
    {
        return ['fecha_inspeccion' => 'date', 'fecha_vencimiento' => 'date'];
    }

    /** Días que faltan para el vencimiento (negativo si ya venció). */
    public function diasParaVencer(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->fecha_vencimiento->copy()->startOfDay(), false);
    }

    /** vigente, por_vencer (60 días o menos), vencida o no_conforme. */
    public function estado(): string
    {
        if ($this->resultado === 'no_conforme') {
            return 'no_conforme';
        }
        $dias = $this->diasParaVencer();

        return $dias < 0 ? 'vencida' : ($dias <= 60 ? 'por_vencer' : 'vigente');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por')->withTrashed();
    }
}
