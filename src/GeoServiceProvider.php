<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities;

use Illuminate\Support\ServiceProvider;
use TwoFaces\LaravelCities\Commands\BuildPplTree;
use TwoFaces\LaravelCities\Commands\ClearGeoDatabase;
use TwoFaces\LaravelCities\Commands\DownloadGeoData;
use TwoFaces\LaravelCities\Commands\ImportJsonFile;
use TwoFaces\LaravelCities\Commands\SeedGeoFile;

class GeoServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->handleConfig();
        $this->handleMigrations();
        $this->handleConsoleCommands();
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laravel-cities.php',
            'laravel-cities'
        );
    }

    /**
     * Handle configuration publishing.
     */
    private function handleConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/laravel-cities.php' => config_path('laravel-cities.php'),
        ], 'config');
    }

    /**
     * Register console commands.
     */
    private function handleConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BuildPplTree::class,
                ClearGeoDatabase::class,
                DownloadGeoData::class,
                ImportJsonFile::class,
                SeedGeoFile::class,
            ]);
        }
    }

    /**
     * Handle database migrations.
     */
    private function handleMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations');

        $this->publishes([
            __DIR__ . '/migrations' => database_path('migrations'),
        ], 'migrations');
    }
}
