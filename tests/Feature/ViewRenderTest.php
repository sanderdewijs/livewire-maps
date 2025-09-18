<?php

it('renders the map blade template content', function () {
    $path = dirname(__DIR__, 2) . '/resources/views/livewire-map.blade.php';
    expect(file_exists($path))->toBeTrue();

    $template = file_get_contents($path);

    // Naively replace the Blade placeholder for domId to simulate rendering
    $html = str_replace('id="{{ $domId }}"', 'id="test-map-123"', $template);

    expect($html)->toContain('id="test-map-123"');
    expect($html)->toContain('lw-google-maps-script');
});
