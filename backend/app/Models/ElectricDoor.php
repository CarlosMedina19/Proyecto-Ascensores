<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectricDoor extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_id',
        'brand',
        'model',
        'door_type',
        'opening_type',
        'access_type',
        'serial_number',
        'technical_specs',
        'installation_date',
    ];

    protected function casts(): array
    {
        return [
            'installation_date' => 'date',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}