<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification as LaravelDatabaseNotification;

class DatabaseNotification extends LaravelDatabaseNotification
{
    use MapsDatabaseAttributes;
}
