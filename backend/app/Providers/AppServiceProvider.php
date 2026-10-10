<?php

namespace App\Providers;

use App\Auth\SpanishPasswordBrokerManager;
use App\Database\SpanishPostgresGrammar;
use App\Database\SpanishSQLiteGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->app->make('auth.password');
        $this->app->singleton('auth.password', fn ($app) => new SpanishPasswordBrokerManager($app));
        $this->app->bind('auth.password.broker', fn ($app) => $app->make('auth.password')->broker());

        Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);

        $connection = DB::connection();

        if (app()->environment('testing') || Schema::hasTable('usuarios')) {
            match ($connection->getDriverName()) {
                'pgsql' => $connection->setQueryGrammar(new SpanishPostgresGrammar($connection)),
                'sqlite' => $connection->setQueryGrammar(new SpanishSQLiteGrammar($connection)),
                default => null,
            };
        }
    }
}