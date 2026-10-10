<?php

namespace App\Auth;

use Illuminate\Auth\Passwords\CacheTokenRepository;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use App\Database\DatabaseNames;

class SpanishPasswordBrokerManager extends PasswordBrokerManager
{
    protected function createTokenRepository(array $config): TokenRepositoryInterface
    {
        $connection = $this->app['db']->connection($config['connection'] ?? null);

        if (($config['driver'] ?? null) === 'cache'
            || ! $connection->getSchemaBuilder()->hasTable(DatabaseNames::tables()['password_reset_tokens'])) {
            return parent::createTokenRepository($config);
        }

        $key = $this->app['config']['app.key'];

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        if (($config['driver'] ?? null) === 'cache') {
            return new CacheTokenRepository(
                $this->app['cache']->store($config['store'] ?? null),
                $this->app['hash'],
                $key,
                ($config['expire'] ?? 60) * 60,
                $config['throttle'] ?? 0,
            );
        }

        return new SpanishDatabaseTokenRepository(
            $connection,
            $this->app['hash'],
            $config['table'],
            $key,
            ($config['expire'] ?? 60) * 60,
            $config['throttle'] ?? 0,
        );
    }
}
