<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * RF-022: perfil del técnico. Su estado (activo/inactivo) es el de su cuenta de usuario.
 */
class Tecnico extends Model
{
    protected $table = 'tecnicos';

    protected $fillable = [
        'usuario_id', 'tipo_documento', 'numero_documento', 'telefono', 'disponibilidad', 'horario',
        'motivo_no_disponible', 'no_disponible_hasta', 'observaciones',
    ];

    protected function casts(): array
    {
        return ['no_disponible_hasta' => 'date', 'horario' => 'array'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class)->withTrashed();
    }

    public function especialidades(): BelongsToMany
    {
        return $this->belongsToMany(Especialidad::class, 'especialidad_tecnico')->orderBy('nombre');
    }

    public function ordenes(): BelongsToMany
    {
        return $this->belongsToMany(OrdenTrabajo::class, 'orden_tecnico', 'tecnico_id', 'orden_id')
            ->withPivot(['asignado_por', 'asignado_en']);
    }

    /** RF-022: solo los técnicos activos y disponibles reciben órdenes. */
    public function scopeAsignables(Builder $consulta): Builder
    {
        return $consulta->where('disponibilidad', 'disponible')
            ->whereHas('usuario', fn ($u) => $u->where('activo', true));
    }

    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    /**
     * El trabajo de inicio a fin cabe en su jornada de ese día. Sin horario registrado, siempre cabe.
     */
    public function trabajaEn(CarbonInterface $inicio, CarbonInterface $fin): bool
    {
        if (empty($this->horario)) {
            return true;
        }
        $jornada = $this->horario[(string) $inicio->isoWeekday()] ?? null;
        if (! $jornada || $inicio->toDateString() !== $fin->toDateString()) {
            return false;
        }

        return $inicio->format('H:i') >= $jornada['inicio'] && $fin->format('H:i') <= $jornada['fin'];
    }

    /** "Lun a vie 07:00–17:00 · sáb 08:00–12:00" */
    public function horarioTexto(): ?string
    {
        if (empty($this->horario)) {
            return null;
        }
        $grupos = [];
        foreach (self::DIAS as $n => $dia) {
            $j = $this->horario[(string) $n] ?? null;
            if (! $j) {
                continue;
            }
            $rango = "{$j['inicio']}–{$j['fin']}";
            $ultimo = array_key_last($grupos);
            if ($ultimo !== null && $grupos[$ultimo]['rango'] === $rango && $grupos[$ultimo]['hasta'] === $n - 1) {
                $grupos[$ultimo]['hasta'] = $n;
            } else {
                $grupos[] = ['desde' => $n, 'hasta' => $n, 'rango' => $rango];
            }
        }
        $corto = fn ($n) => mb_strtolower(mb_substr(self::DIAS[$n], 0, 3));

        return ucfirst(implode(' · ', array_map(fn ($g) => ($g['desde'] === $g['hasta'] ? $corto($g['desde']) : $corto($g['desde']).' a '.$corto($g['hasta']))." {$g['rango']}", $grupos)));
    }

    public function puedeRecibirOrdenes(): bool
    {
        return $this->disponibilidad === 'disponible' && (bool) $this->usuario?->activo;
    }
}
