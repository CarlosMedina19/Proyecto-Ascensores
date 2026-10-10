<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

abstract class DatabaseAuthenticatable extends Authenticatable
{
    use MapsDatabaseAttributes;
}
