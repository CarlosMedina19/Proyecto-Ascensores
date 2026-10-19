<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrecioPlan extends Model
{
    protected $table = 'precios_plan';

    protected $fillable = ['plan_id', 'periodicidad_id', 'precio'];

    protected function casts(): array
    {
        return ['precio' => 'decimal:2'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanMantenimiento::class, 'plan_id');
    }

    public function periodicidad(): BelongsTo
    {
        return $this->belongsTo(Periodicidad::class, 'periodicidad_id');
    }
}
