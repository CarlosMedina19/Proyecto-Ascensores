<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    use HasFactory;

    protected $table = 'contactos_cliente';

    protected $fillable = ['cliente_id', 'nombre', 'cargo', 'telefono', 'correo'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'cliente_id');
    }
}
