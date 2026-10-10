<?php

namespace App\Database;

use Illuminate\Database\Query\Grammars\PostgresGrammar;

class SpanishPostgresGrammar extends PostgresGrammar
{
    use MapsDatabaseIdentifiers;
}
