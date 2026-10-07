<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Elevator extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_id',
        'brand',
        'model',
        'capacity_kg',
        'speed_mpm',
        'stops',
        'drive_type',
        'motor',
        'controller',
        'door_type',
        'technical_specs',
        'installation_date',
    ];

    protected function casts(): array
    {
        return [
            'installation_date' => 'date',
            'capacity_kg' => 'integer',
            'speed_mpm' => 'decimal:2',
            'stops' => 'integer',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}