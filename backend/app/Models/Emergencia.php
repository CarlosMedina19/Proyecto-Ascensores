<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Emergencia o atrapamiento reportado 24/7, con tiempo objetivo de llegada y seguimiento. */
class Emergencia extends Model
{
    protected $table = 'emergencias';

    public const TIPOS = ['atrapamiento' => 'Personas atrapadas', 'falla' => 'Ascensor detenido o con falla', 'ruido' => 'Ruido o movimiento anormal', 'otro' => 'Otro'];

    public const ORIGENES = ['panel' => 'Panel', 'app' => 'App', 'qr' => 'Código QR'];

    public const ESTADOS = ['reportada' => 'Reportada', 'asignada' => 'Técnico en camino', 'en_sitio' => 'Técnico en sitio', 'resuelta' => 'Resuelta', 'cancelada' => 'Cancelada'];

    /** Estados siguientes permitidos. */
    public const TRANSICIONES = [
        'reportada' => ['asignada', 'en_sitio', 'resuelta', 'cancelada'],
        'asignada' => ['en_sitio', 'resuelta', 'cancelada'],
        'en_sitio' => ['resuelta'],
        'resuelta' => [],
        'cancelada' => [],
    ];

    protected $fillable = [
        'numero', 'equipo_id', 'tipo', 'origen', 'personas_atrapadas', 'reportante_nombre', 'reportante_telefono',
        'descripcion', 'estado', 'minutos_objetivo', 'tecnico_id', 'orden_id', 'reportada_en', 'asignada_en',
        'llegada_en', 'resuelta_en', 'solucion', 'registrada_por',
    ];

    protected function casts(): array
    {
        return [
            'personas_atrapadas' => 'boolean',
            'reportada_en' => 'datetime',
            'asignada_en' => 'datetime',
            'llegada_en' => 'datetime',
            'resuelta_en' => 'datetime',
        ];
    }

    public function abierta(): bool
    {
        return in_array($this->estado, ['reportada', 'asignada', 'en_sitio'], true);
    }

    /** Minutos desde el reporte hasta la llegada del técnico (hasta ahora si sigue abierta sin llegada). */
    public function minutosRespuesta(): ?int
    {
        if ($this->llegada_en) {
            return (int) $this->reportada_en->diffInMinutes($this->llegada_en);
        }

        return $this->abierta() ? (int) $this->reportada_en->diffInMinutes(now()) : null;
    }

    /** true o false cuando ya hubo llegada o se pasó del plazo; null mientras sigue en plazo. */
    public function dentroDelObjetivo(): ?bool
    {
        $minutos = $this->minutosRespuesta();
        if ($minutos === null) {
            return null;
        }
        if (! $this->llegada_en && $minutos <= $this->minutos_objetivo) {
            return null;
        }

        return $minutos <= $this->minutos_objetivo;
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Tecnico::class, 'tecnico_id');
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(SeguimientoEmergencia::class, 'emergencia_id')->orderBy('created_at')->orderBy('id');
    }
}
