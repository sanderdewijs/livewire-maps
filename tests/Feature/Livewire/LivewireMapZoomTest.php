<?php

use Sdw\LivewireMaps\Livewire\LivewireMap;

class DummyLivewireMapForZoomTest extends LivewireMap
{
    public array $lastDispatch = [];

    public function dispatch($event, ...$params)
    {
        // Map positional params back to expected keys based on how the component dispatches
        $payload = [
            'event' => $event,
            'id' => $params[0] ?? null,
            'markers' => $params[1] ?? null,
            'useClusters' => $params[2] ?? null,
            'clusterOptions' => $params[3] ?? null,
            'centerLat' => null,
            'centerLng' => null,
            'zoom' => null,
        ];

        // Examine trailing arguments for numeric values to infer center and zoom
        $tail = array_slice($params, 4);
        $nums = [];
        foreach ($tail as $v) {
            if (is_int($v) || is_float($v) || (is_numeric($v))) {
                $nums[] = $v + 0; // cast numeric strings to number
            }
        }
        if (count($nums) >= 2) {
            $payload['centerLat'] = (float) $nums[0];
            $payload['centerLng'] = (float) $nums[1];
        }
        if (count($nums) >= 3) {
            $payload['zoom'] = (int) $nums[2];
        } elseif (count($nums) === 1) {
            $payload['zoom'] = (int) $nums[0];
        }

        $this->lastDispatch = $payload;
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
    expect($comp->lastDispatch['centerLat'])->toBeNull();
    expect($comp->lastDispatch['centerLng'])->toBeNull();
});
