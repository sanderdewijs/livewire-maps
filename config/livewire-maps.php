<?php

return [
    // Google Maps API key (BC: still supported; prefer 'google_maps_key')
    'api_key' => env('GOOGLE_MAPS_API_KEY', null),

    // Default zoom level when none is provided to the component
    'default_zoom' => 8,

    // Default map center coordinates
    'default_center' => [
        'lat' => 0.0,
        'lng' => 0.0,
    ],

    // Default map container dimensions
    'default_width' => '100%',
    'default_height' => '400px',

    // Whether to use marker clustering by default
    'use_clusters' => false,

    // Default options passed to the Google Maps Map instance
    'map_options' => [
        // e.g. 'disableDefaultUI' => true,
    ],

    // Default options for the MarkerClusterer (when clustering is enabled)
    'cluster_options' => [
        // e.g. 'maxZoom' => 14,
    ],

    // --- New options for robust JS loading ---
    // How to load the package JS: 'vite' | 'mix' | 'cdn' | 'file' | 'none'
    'asset_driver' => env('LW_MAPS_ASSET_DRIVER', 'file'),

    // Should the directive also load the Google Maps API script?
    'load_google_maps' => env('LW_MAPS_LOAD_GOOGLE', true),

    // Preferred key for Google Maps API; falls back to 'api_key' above
    'google_maps_key' => env('LW_MAPS_GOOGLE_KEY', env('GOOGLE_MAPS_API_KEY', null)),

    // Comma separated libraries to load with Google Maps API
    'google_maps_libraries' => env('LW_MAPS_GOOGLE_LIBS', 'drawing,geometry'),

    // Locale/language for Google Maps UI (e.g. 'nl', 'en')
    'locale' => env('LW_MAPS_LOCALE', null),

    // CDN URL to the package JS (when asset_driver = 'cdn')
    'cdn_url' => env('LW_MAPS_CDN_URL', null),

    // Vite entry used by @vite (when asset_driver = 'vite')
    'vite_entry' => env('LW_MAPS_VITE_ENTRY', 'resources/js/livewire-maps.js'),

    // Mix path passed to mix() (when asset_driver = 'mix')
    'mix_path' => env('LW_MAPS_MIX_PATH', '/vendor/livewire-maps/livewire-maps.js'),
];
