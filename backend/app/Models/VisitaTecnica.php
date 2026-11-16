<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Planilla de asistencia técnica diligenciada por el técnico en una visita. */
class VisitaTecnica extends Model
{
    protected $table = 'visitas_tecnicas';

    public const TIPOS = ['preventivo' => 'Mantenimiento preventivo', 'correctivo' => 'Mantenimiento correctivo', 'reparacion' => 'Reparación'];

    protected $fillable = [
        'numero', 'equipo_id', 'edificio_id', 'tecnico_usuario_id', 'orden_id', 'tipo_servicio', 'entrada_en', 'salida_en',
        'secciones', 'observaciones', 'repuestos', 'recibe_nombre', 'recibe_documento', 'tecnico_documento',
        'firma_tecnico_ruta', 'firma_recibe_ruta', 'pdf_ruta',
    ];

    protected function casts(): array
    {
        return [
            'entrada_en' => 'datetime',
            'salida_en' => 'datetime',
            'secciones' => 'array',
            'repuestos' => 'array',
        ];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class, 'edificio_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tecnico_usuario_id')->withTrashed();
    }

    /** Conteo de estados B, R y M y de actividades que no aplican. */
    public function conteo(): array
    {
        $conteo = ['B' => 0, 'R' => 0, 'M' => 0, 'NA' => 0];
        foreach ($this->secciones ?? [] as $seccion) {
            foreach ($seccion['items'] ?? [] as $item) {
                if (! empty($item['na'])) {
                    $conteo['NA']++;
                } elseif (isset($conteo[$item['estado'] ?? ''])) {
                    $conteo[$item['estado']]++;
                }
            }
        }

        return $conteo;
    }

    public function resumen(): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'tipo_servicio' => $this->tipo_servicio,
            'tipo_nombre' => self::TIPOS[$this->tipo_servicio] ?? $this->tipo_servicio,
            'entrada_en' => $this->entrada_en?->toIso8601String(),
            'salida_en' => $this->salida_en?->toIso8601String(),
            'tecnico' => $this->tecnico?->nombre,
            'recibe_nombre' => $this->recibe_nombre,
            'conteo' => $this->conteo(),
            'ruta_pdf' => "/visitas/{$this->id}/pdf",
        ];
    }
}
