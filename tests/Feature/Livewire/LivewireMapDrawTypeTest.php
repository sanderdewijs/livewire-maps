<?php

use Sdw\LivewireMaps\Livewire\LivewireMap;

class DummyLivewireMapForDrawTypeTest extends LivewireMap
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

it('forwards drawType in the lw-map-internal-update payload when provided', function () {
    $comp = new DummyLivewireMapForDrawTypeTest();

    $comp->onMapUpdate(
        markers: [],
        useClusters: false,
        clusterOptions: [],
        center: ['lat' => 52.1, 'lng' => 5.1],
        zoom: 14,
        drawType: 'circle',
    );

    expect($comp->lastDispatch['event'])->toBe('lw-map-internal-update');
    expect($comp->lastDispatch['drawType'])->toBe('circle');
});

it('omits drawType when not provided', function () {
    $comp = new DummyLivewireMapForDrawTypeTest();

    $comp->onMapUpdate(
        markers: [],
        useClusters: false,
        clusterOptions: [],
        center: ['lat' => 52.1, 'lng' => 5.1],
        zoom: 14,
    );

    expect($comp->lastDispatch['event'])->toBe('lw-map-internal-update');
    expect(array_key_exists('drawType', $comp->lastDispatch))->toBeFalse();
});
