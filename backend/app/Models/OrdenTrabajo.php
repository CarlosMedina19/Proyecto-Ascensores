<?php

namespace App\Models;

use App\Models\Concerns\TieneUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * RF-025 a RF-029: orden de trabajo con estados, prioridad, tiempos y trazabilidad.
 */
class OrdenTrabajo extends Model
{
    use TieneUuid;

    protected $table = 'ordenes_trabajo';

    public const SERVICIOS = ['preventivo', 'correctivo', 'inspeccion'];

    public const PRIORIDADES = ['baja', 'media', 'alta', 'critica'];

    public const ESTADOS = ['programada', 'asignada', 'en_camino', 'en_proceso', 'pendiente', 'finalizada', 'cancelada'];

    /** Estados que ya no cambian (RF-026). */
    public const FINALES = ['finalizada', 'cancelada'];

    /** RF-026: solo se cancela en estos estados. */
    public const CANCELABLES = ['programada', 'asignada', 'pendiente'];

    protected $fillable = [
        'numero', 'servicio', 'cliente_id', 'edificio_id', 'equipo_id', 'mantenimiento_id', 'fecha', 'hora',
        'duracion_estimada', 'prioridad', 'estado', 'descripcion', 'observaciones', 'informe', 'motivo_cancelacion',
        'inicio_en', 'fin_en', 'duracion_minutos', 'creado_por', 'orden_origen_id', 'resultado_origen_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'duracion_estimada' => 'integer',
            'inicio_en' => 'datetime',
            'fin_en' => 'datetime',
            'duracion_minutos' => 'integer',
        ];
    }

    public function scopeActivas(Builder $consulta): Builder
    {
        return $consulta->whereNotIn('estado', self::FINALES);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class);
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class)->withTrashed();
    }

    public function mantenimiento(): BelongsTo
    {
        return $this->belongsTo(MantenimientoProgramado::class, 'mantenimiento_id');
    }

    public function tecnicos(): BelongsToMany
    {
        return $this->belongsToMany(Tecnico::class, 'orden_tecnico', 'orden_id', 'tecnico_id')
            ->withPivot(['asignado_por', 'asignado_en']);
    }

    public function trazabilidad(): HasMany
    {
        return $this->hasMany(TrazabilidadOrden::class, 'orden_id');
    }

    /** Fase 4: planilla del mantenimiento preventivo (RF-030). */
    public function planilla(): HasOne
    {
        return $this->hasOne(Planilla::class, 'orden_id');
    }

    public function firmas(): HasMany
    {
        return $this->hasMany(FirmaOrden::class, 'orden_id');
    }

    public function informes(): HasMany
    {
        return $this->hasMany(InformeOrden::class, 'orden_id')->orderByDesc('version');
    }

    /** RF-038: orden y ítem que originaron este correctivo. */
    public function ordenOrigen(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_origen_id');
    }

    public function resultadoOrigen(): BelongsTo
    {
        return $this->belongsTo(PlanillaResultado::class, 'resultado_origen_id');
    }

    public function correctivosDerivados(): HasMany
    {
        return $this->hasMany(OrdenTrabajo::class, 'orden_origen_id');
    }

    /** RF-030: la planilla se diligencia solo en órdenes preventivas. */
    public function llevaPlanilla(): bool
    {
        return $this->servicio === 'preventivo';
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por')->withTrashed();
    }

    public function esFinal(): bool
    {
        return in_array($this->estado, self::FINALES, true);
    }

    public function inicioProgramado(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->fecha->toDateString().' '.substr((string) $this->hora, 0, 5));
    }

    public function finProgramado(): CarbonImmutable
    {
        return $this->inicioProgramado()->addMinutes($this->duracion_estimada);
    }

    public function tieneAsignado(?Usuario $usuario): bool
    {
        return $usuario !== null && $this->tecnicos()->where('usuario_id', $usuario->id)->exists();
    }
}
