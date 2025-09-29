<?php

use Sdw\LivewireMaps\Livewire\LivewireMap;

class DummyLivewireMapForDrawTypeTest extends LivewireMap
{
    public array $lastDispatch = [];

    public function dispatch($event, ...$params)
    {
        $payload = [
            'event' => $event,
            'id' => $params[0] ?? null,
            'markers' => $params[1] ?? null,
            'useClusters' => $params[2] ?? null,
            'clusterOptions' => $params[3] ?? null,
            'drawType' => null,
        ];

        // The dispatch call uses named parameters in a fixed order.
        // drawType, when provided, is the last argument and is a string (not equal to id which is first)
        if (!empty($params)) {
            $last = $params[count($params) - 1];
            if (is_string($last) && $last !== $payload['id']) {
                $payload['drawType'] = $last;
            }
        }

        $this->lastDispatch = $payload;
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
    expect($comp->lastDispatch['drawType'])->toBeNull();
});
