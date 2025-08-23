<?php

namespace Sdw\LivewireMaps\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sdw\LivewireMaps\LivewireMapServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        $providers = [
            LivewireMapServiceProvider::class,
        ];

        // Include Livewire provider if available
        if (class_exists(\Livewire\LivewireServiceProvider::class)) {
            $providers[] = \Livewire\LivewireServiceProvider::class;
        }

        return $providers;
    }
}
