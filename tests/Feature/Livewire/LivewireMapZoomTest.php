<?php

use Sdw\LivewireMaps\Livewire\LivewireMap;

class DummyLivewireMapForZoomTest extends LivewireMap
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

it('forwards zoom in the lw-map-internal-update payload when provided with center', function () {
    $comp = new DummyLivewireMapForZoomTest();

    $comp->onMapUpdate(
        markers: [ ['lat' => 52.0, 'lng' => 5.0] ],
        useClusters: true,
        clusterOptions: ['foo' => 'bar'],
        center: ['lat' => 52.1, 'lng' => 5.1],
        zoom: 12,
    );

    expect($comp->lastDispatch['event'])->toBe('lw-map-internal-update');
    expect($comp->lastDispatch['zoom'])->toBe(12);
    expect($comp->zoom)->toBe(12);
    expect($comp->lastDispatch['centerLat'])->toBe(52.1);
    expect($comp->lastDispatch['centerLng'])->toBe(5.1);
});

it('forwards zoom even when center is not provided', function () {
    $comp = new DummyLivewireMapForZoomTest();

    $comp->onMapUpdate(
        markers: [],
        useClusters: false,
        clusterOptions: [],
        center: [],
        zoom: 9,
    );

    expect($comp->lastDispatch['event'])->toBe('lw-map-internal-update');
    expect($comp->lastDispatch['zoom'])->toBe(9);
    expect($comp->zoom)->toBe(9);
    // No center should be forwarded
    expect(array_key_exists('centerLat', $comp->lastDispatch))->toBeFalse();
    expect(array_key_exists('centerLng', $comp->lastDispatch))->toBeFalse();
});
