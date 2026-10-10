<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentHistory extends DatabaseModel
{
    protected $table = 'equipment_history';

    public $timestamps = false; // solo tenemos created_at

    protected $fillable = ['equipment_id', 'user_id', 'event', 'description'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
