<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities\Tests\Feature;

use TwoFaces\LaravelCities\GeoServiceProvider;
use TwoFaces\LaravelCities\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_service_provider_is_loaded(): void
    {
        $providers = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(GeoServiceProvider::class, $providers);
    }

    public function test_config_is_published(): void
    {
        $configPath = __DIR__ . '/../../config/laravel-cities.php';

        $this->assertFileExists($configPath);
    }

    public function test_config_has_required_keys(): void
    {
        $config = config('laravel-cities');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('geonames_url', $config);
        $this->assertArrayHasKey('storage_path', $config);
        $this->assertArrayHasKey('table_name', $config);
        $this->assertArrayHasKey('chunk_size', $config);
        $this->assertArrayHasKey('import_levels', $config);
        $this->assertArrayHasKey('files', $config);
    }

    public function test_config_has_valid_defaults(): void
    {
        $config = config('laravel-cities');

        $this->assertEquals('https://download.geonames.org/export/dump', $config['geonames_url']);
        $this->assertEquals('geo', $config['storage_path']);
        $this->assertEquals('geo', $config['table_name']);
        $this->assertEquals(1000, $config['chunk_size']);
        $this->assertIsArray($config['import_levels']);
        $this->assertNotEmpty($config['import_levels']);
    }

    public function test_migrations_are_loaded(): void
    {
        $migrationPath = __DIR__ . '/../../src/migrations';

        $this->assertDirectoryExists($migrationPath);

        $migrations = glob($migrationPath . '/*.php');

        $this->assertNotEmpty($migrations);
    }
}

