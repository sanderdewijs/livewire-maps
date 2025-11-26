<?php

use Sdw\LivewireMaps\Livewire\LivewireMap;

class DummyLivewireMapForRadiusTest extends LivewireMap
{
    public array $lastDispatch = [];

    public function dispatch($event, ...$params)
    {
        $this->lastDispatch = [
            'event' => $event,
            ...$params,
        ];
        return null;
    }
}

it('forwards radius in the lw-map-internal-update payload when provided with center', function () {
    $comp = new DummyLivewireMapForRadiusTest();

    $comp->onMapUpdate(
        markers: [['lat' => 52.0, 'lng' => 5.0]],
        useClusters: false,
        clusterOptions: [],
        center: ['lat' => 52.0907, 'lng' => 5.1214],
        zoom: 10,
        drawType: null,
        radius: 5000,
        radiusOptions: ['color' => '#ff0000'],
    );

    expect($comp->lastDispatch['event'])->toBe('lw-map-internal-update');
    expect($comp->lastDispatch['radius'])->toBe(5000);
    expect($comp->lastDispatch['radiusOptions'])->toBe(['color' => '#ff0000']);
    expect($comp->lastDispatch['centerLat'])->toBe(52.0907);
    expect($comp->lastDispatch['centerLng'])->toBe(5.1214);
});

it('omits radius from payload when not provided', function () {
    $comp = new DummyLivewireMapForRadiusTest();

    $comp->onMapUpdate(
        markers: [],
        useClusters: false,
        clusterOptions: [],
        center: ['lat' => 52.0907, 'lng' => 5.1214],
    );

    expect($comp->lastDispatch['event'])->toBe('lw-map-internal-update');
    expect(array_key_exists('radius', $comp->lastDispatch))->toBeFalse();
    expect(array_key_exists('radiusOptions', $comp->lastDispatch))->toBeFalse();
});

it('includes empty radiusOptions when radius is provided without options', function () {
    $comp = new DummyLivewireMapForRadiusTest();

    $comp->onMapUpdate(
        markers: [],
        useClusters: false,
        clusterOptions: [],
        center: ['lat' => 52.0, 'lng' => 5.0],
        radius: 10000,
    );

    expect($comp->lastDispatch['event'])->toBe('lw-map-internal-update');
    expect($comp->lastDispatch['radius'])->toBe(10000);
    expect($comp->lastDispatch['radiusOptions'])->toBe([]);
});
