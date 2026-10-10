<?php

namespace App\Database;

use Illuminate\Database\Query\Grammars\SQLiteGrammar;

class SpanishSQLiteGrammar extends SQLiteGrammar
{
    use MapsDatabaseIdentifiers;
}
