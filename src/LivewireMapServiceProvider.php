<?php

namespace Sdw\LivewireMaps;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class LivewireMapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config so users can override published values when present during development
        $configPath = __DIR__ . '/../config/livewire-maps.php';
        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'livewire-maps');
        }
    }

    public function boot(): void
    {
        // Load package views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'livewire-maps');

        // Register Blade include directive for scripts: @LwMapsScripts
        Blade::include('livewire-maps::scripts', 'LwMapsScripts');

        // Allow users to publish the config file
        $this->publishes([
            __DIR__ . '/../config/livewire-maps.php' => config_path('livewire-maps.php'),
        ], 'livewire-maps-config');

        // Also publish with the generic 'config' tag for convenience
        $this->publishes([
            __DIR__ . '/../config/livewire-maps.php' => config_path('livewire-maps.php'),
        ], 'config');

        // Publish JS assets to public/vendor
        $this->publishes([
            __DIR__ . '/../resources/js/livewire-maps.js' => public_path('vendor/livewire-maps/livewire-maps.js'),
        ], 'livewire-maps-assets');
        // Allow publishing with generic 'public' tag as well
        $this->publishes([
            __DIR__ . '/../resources/js/livewire-maps.js' => public_path('vendor/livewire-maps/livewire-maps.js'),
        ], 'public');

        // Register console commands (including an overwrite publish command)
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Sdw\LivewireMaps\Console\Commands\PublishAssetsCommand::class,
            ]);
        }

        // Defer Livewire component registration until all providers are booted
        $this->app->booted(function () {
            if (class_exists('Livewire\\Livewire') && $this->app->bound('livewire')) {
                \Livewire\Livewire::component('livewire-map', \Sdw\LivewireMaps\Livewire\LivewireMap::class);
            }
        });
    }
}
