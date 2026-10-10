<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends DatabaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'type',
        'name',
        'document_type',
        'document_number',
        'nit',
        'address',
        'phone',
        'email',
        'tax_regime',
        'economic_activity',
        'status',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
    
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }
}