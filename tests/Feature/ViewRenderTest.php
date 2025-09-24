<?php

it('renders the map blade template content', function () {
    $path = dirname(__DIR__, 2) . '/resources/views/livewire-map.blade.php';
    expect(file_exists($path))->toBeTrue();

    $template = file_get_contents($path);

    // Naively replace the Blade placeholder for domId to simulate rendering
    $html = str_replace('id="{{ $domId }}"', 'id="test-map-123"', $template);

    expect($html)->toContain('id="test-map-123"');
    // Container should be marked for discovery with data attributes (no inline init)
    expect($html)->toContain('data-lw-map');
    expect($html)->toContain('data-lat="');
    expect($html)->toContain('data-lng="');
    expect($html)->toContain('data-zoom="');
});

it('renders the map container with id, wire:ignore, and size styles', function () {
    $path = dirname(__DIR__, 2) . '/resources/views/livewire-map.blade.php';
    expect(file_exists($path))->toBeTrue();

    $template = file_get_contents($path);

    // Simulate Blade variable interpolation for key attributes
    $html = str_replace('id="{{ $domId }}"', 'id="test-map-456"', $template);
    $html = str_replace('style="width: {{ $width }}; height: {{ $height }};"', 'style="width: 600px; height: 300px;"', $html);

    // We expect the opening container div to include our simulated values
    expect($html)->toContain('<div id="test-map-456"');
    expect($html)->toContain('wire:ignore');
    expect($html)->toContain('data-lw-map');
    expect($html)->toContain('style="width: 600px; height: 300px;"');
});
