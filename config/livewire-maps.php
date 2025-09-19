<?php

return [
    // Google Maps API key
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
];
