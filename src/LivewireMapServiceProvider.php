<?php

namespace Sdw\LivewireMaps;

use Illuminate\Support\ServiceProvider;

class LivewireMapServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Load package views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'livewire-maps');

        // Defer Livewire component registration until all providers are booted
        $this->app->booted(function () {
            if (class_exists('Livewire\\Livewire') && $this->app->bound('livewire')) {
                \Livewire\Livewire::component('livewire-map', \Sdw\LivewireMaps\Livewire\LivewireMap::class);
            }
        });
    }
}
