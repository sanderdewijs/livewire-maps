<?php

it('renders the map blade view with required variables', function () {
    $html = view('livewire-maps::livewire-map', [
        'domId' => 'test-map-123',
        'width' => '100%',
        'height' => '400px',
        'apiKey' => 'fake-key',
        'zoom' => 8,
        'centerLat' => 0,
        'centerLng' => 0,
        'useClusters' => false,
        'mapOptions' => [],
        'clusterOptions' => [],
        'normalizedMarkers' => [],
    ])->render();

    expect($html)->toContain('id="test-map-123"');
    expect($html)->toContain('lw-google-maps-script');
});
