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

    // Optional placeholder image to show before the map is initialized
    // Provide a full URL or path resolvable by the browser. Set null to disable.
    'maps_placeholder_img' => env('LW_MAPS_PLACEHOLDER_IMG', null),

    // Whether to use marker clustering by default
    'use_clusters' => false,

    // Automatically fit the map to the bounds of all provided markers (when any markers exist)
    // Set to false to disable auto-fit and keep center/zoom behavior.
    'auto_fit_bounds' => env('LW_MAPS_AUTO_FIT_BOUNDS', true),

    // Default options passed to the Google Maps Map instance
    'map_options' => [
        // e.g. 'disableDefaultUI' => true,
    ],

    // Default options for the MarkerClusterer (when clustering is enabled)
    'cluster_options' => [
        // e.g. 'maxZoom' => 14,
    ],

    // Optional: Defer map initialization until a custom browser event fires.
    // Provide a string event name (e.g. 'my-app:maps:init') or leave null for immediate init.
    // You can also override per component via the mount() parameter $initEvent.
    'init_event' => env('LW_MAPS_INIT_EVENT', null),

    // --- New options for robust JS loading ---
    // How to load the package JS: 'vite' | 'mix' | 'cdn' | 'file' | 'none'
    'asset_driver' => env('LW_MAPS_ASSET_DRIVER', 'file'),

    // Should the directive also load the Google Maps API script?
    'load_google_maps' => env('LW_MAPS_LOAD_GOOGLE', true),

    // Preferred key for Google Maps API; falls back to 'api_key' above
    'google_maps_key' => env('LW_MAPS_GOOGLE_KEY', env('GOOGLE_MAPS_API_KEY', null)),

    // Comma separated libraries to load with Google Maps API
    'google_maps_libraries' => env('LW_MAPS_GOOGLE_LIBS', 'geometry'),

    // Locale/language for Google Maps UI (e.g. 'nl', 'en')
    'locale' => env('LW_MAPS_LOCALE', null),

    // CDN URL to the package JS (when asset_driver = 'cdn')
    'cdn_url' => env('LW_MAPS_CDN_URL', null),

    // Should the TerraDraw scripts be loaded automatically?
    'load_terra_draw' => env('LW_MAPS_LOAD_TERRADRAW', true),

    // CDN URLs for TerraDraw core and the Google Maps adapter
    'terra_draw_core_url' => env('LW_MAPS_TERRADRAW_CORE_URL', 'https://cdn.jsdelivr.net/npm/@terradraw/core@1.7.4/dist/terradraw.umd.js'),
    'terra_draw_google_adapter_url' => env('LW_MAPS_TERRADRAW_ADAPTER_URL', 'https://cdn.jsdelivr.net/npm/@terradraw/google-maps-adapter@1.7.4/dist/terradraw-google-maps-adapter.umd.js'),

    // Vite entry used by @vite (when asset_driver = 'vite')
    'vite_entry' => env('LW_MAPS_VITE_ENTRY', 'resources/js/livewire-maps.js'),

    // Mix path passed to mix() (when asset_driver = 'mix')
    'mix_path' => env('LW_MAPS_MIX_PATH', '/vendor/livewire-maps/livewire-maps.js'),
];
