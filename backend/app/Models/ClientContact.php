<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends DatabaseModel
{
    use HasFactory;

    protected $fillable = ['client_id', 'name', 'position', 'phone', 'email'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
