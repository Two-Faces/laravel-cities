<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use TwoFaces\LaravelCities\GeoServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../src/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            GeoServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup laravel-cities config
        $app['config']->set('laravel-cities.table_name', 'geo');
        $app['config']->set('laravel-cities.storage_path', 'geo');
    }
}
