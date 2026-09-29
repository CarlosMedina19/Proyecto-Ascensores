<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['building_id', 'code', 'type', 'location', 'status'];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function elevator(): HasOne
    {
        return $this->hasOne(Elevator::class);
    }

    public function electricDoor(): HasOne
    {
        return $this->hasOne(ElectricDoor::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(EquipmentHistory::class);
    }
}
