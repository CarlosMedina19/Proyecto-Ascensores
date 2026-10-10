<?php

namespace App\Database;

trait MapsDatabaseIdentifiers
{
    protected function wrapValue($value)
    {
        return parent::wrapValue(DatabaseNames::translate($value));
    }
}
