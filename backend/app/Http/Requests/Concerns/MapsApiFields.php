<?php

namespace App\Http\Requests\Concerns;

trait MapsApiFields
{
    protected function mapApiFields(array $mapping, array $values): array
    {
        foreach ($mapping as $apiField => $databaseColumn) {
            if (array_key_exists($apiField, $values) && ! array_key_exists($databaseColumn, $values)) {
                $values[$databaseColumn] = $values[$apiField];
            }
        }

        return $values;
    }
}
