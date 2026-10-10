<?php

namespace App\Models;

use App\Database\DatabaseNames;

trait MapsDatabaseAttributes
{
    public function setRawAttributes(array $attributes, $sync = false)
    {
        $englishColumns = DatabaseNames::englishColumns();
        $attributes = array_combine(
            array_map(static fn (string|int $key): string|int => $englishColumns[$key] ?? $key, array_keys($attributes)),
            array_values($attributes)
        );

        return parent::setRawAttributes($attributes, $sync);
    }
}
