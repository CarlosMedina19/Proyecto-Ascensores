<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Equipment extends DatabaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'building_id',
        'code',
        'type',
        'brand',
        'model',
        'serial_number',
        'location',
        'status',
        'installation_date',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'installation_date' => 'date',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function elevator(): HasOne
    {
        return $this->hasOne(Elevator::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(EquipmentHistory::class);
    }
}