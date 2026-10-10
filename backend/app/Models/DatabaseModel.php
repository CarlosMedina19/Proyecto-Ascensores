<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class DatabaseModel extends Model
{
    use MapsDatabaseAttributes;
}
