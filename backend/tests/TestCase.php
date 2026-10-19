<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Las pruebas borran la base de datos (RefreshDatabase). Si el contenedor define
     * DB_DATABASE apuntando a la base de desarrollo, se detienen antes de tocarla.
     */
    public function createApplication()
    {
        $app = parent::createApplication();
        $conexion = $app['config']->get('database.default');
        $base = (string) $app['config']->get("database.connections.{$conexion}.database");

        if ($conexion !== 'sqlite' && ! str_ends_with($base, '_test')) {
            throw new \RuntimeException("Las pruebas solo se ejecutan sobre una base de datos *_test (se recibió «{$base}»).");
        }

        return $app;
    }
}
