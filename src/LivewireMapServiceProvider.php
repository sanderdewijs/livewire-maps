<?php

namespace Sdw\LivewireMaps;

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

        // Allow users to publish the config file
        $this->publishes([
            __DIR__ . '/../config/livewire-maps.php' => config_path('livewire-maps.php'),
        ], 'livewire-maps-config');

        // Also publish with the generic 'config' tag for convenience
        $this->publishes([
            __DIR__ . '/../config/livewire-maps.php' => config_path('livewire-maps.php'),
        ], 'config');

        // Defer Livewire component registration until all providers are booted
        $this->app->booted(function () {
            if (class_exists('Livewire\\Livewire') && $this->app->bound('livewire')) {
                \Livewire\Livewire::component('livewire-map', \Sdw\LivewireMaps\Livewire\LivewireMap::class);
            }
        });
    }
}
