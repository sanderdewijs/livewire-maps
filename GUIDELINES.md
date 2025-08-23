# Livewire Google Maps Component (Package Guidelines)

This package provides a Laravel Livewire component that integrates Google Maps with support for markers, optional clustering (MarkerClusterer), and event-driven updates. It is designed to render once and not be re-rendered by Livewire updates or parent component changes.

Component tag:

- <livewire:livewire-map ... />

## Installation

- Require the package in your Laravel project (after publishing this package):
  composer require sanderdewijs/lara-livewire-maps

- The Service Provider is auto-discovered. If needed, register manually in config/app.php:
  Sdw\LivewireMaps\LivewireMapServiceProvider::class

## Attributes (Props)

- apiKey: string (Google Maps API key)
- zoom: int (default: 8)
- centerLat: float (default: 0.0)
- centerLng: float (default: 0.0)
- width: string CSS width (default: 100%)
- height: string CSS height (default: 400px)
- useClusters: bool (default: false)
- mapOptions: array (passed to new google.maps.Map alongside center and zoom)
- clusterOptions: array (passed to new MarkerClusterer)
- markers: array of marker objects

Notes:
- The map container uses wire:ignore to avoid Livewire-driven DOM updates. The JS initializes once per component instance.

## Marker object shape

Each marker must include coordinates via one of the following:
- lat_lng: string "lat,lng" OR array [lat, lng]
- or lat and lng separated as numbers

Optional fields:
- label_content: HTML string rendered in a Google InfoWindow when the marker is clicked
- title: string (marker title)
- icon: string (URL to custom marker icon)

Example markers array in a controller or Livewire parent:
$markers = [
    [ 'lat_lng' => '52.0907,5.1214', 'label_content' => '<strong>Utrecht</strong>', 'title' => 'Utrecht' ],
    [ 'lat' => 52.3676, 'lng' => 4.9041, 'label_content' => '<em>Amsterdam</em>' ],
];

## Basic usage in Blade

<livewire:livewire-map
    apiKey="YOUR_GOOGLE_MAPS_API_KEY"
    :zoom="7"
    :center-lat="52.1"
    :center-lng="4.3"
    width="100%"
    height="480px"
    :use-clusters="true"
    :markers="$markers"
    :map-options="['mapTypeId' => 'roadmap']"
    :cluster-options="['gridSize' => 60]"
/>

Multiple maps can be used on the same page; each instance is isolated.

## Event-driven marker updates

The component listens for three kinds of update events to update markers (and optionally re-cluster) without re-rendering the component:

- Element-level CustomEvent dispatched on the map container element.
- Window-level CustomEvent dispatched on window.
- Livewire JS bus event named 'lw-map:update' (works with Livewire v2 and v3 style .on listeners).

Payload shape (detail data):
{
  id?: string   // optional: component DOM id to target a specific map
  markers?: array // new markers (same shape as above)
  useClusters?: bool // override clustering on update
  clusterOptions?: array // optional clusterer options
}

How to obtain the component's DOM id:
- The component dispatches 'lw-map:ready' once initialized. Listen to it to capture the id. For example:

document.addEventListener('lw-map:ready', function(e) {
  console.log('Map ready id:', e.detail.id);
});

### Dispatching an element-level update

// Suppose you saved the id from 'lw-map:ready' in variable mapId
var el = document.getElementById(mapId);
el.dispatchEvent(new CustomEvent('lw-map:update', {
  detail: {
    id: mapId,
    markers: [ { lat: 52.09, lng: 5.12, label_content: '<b>Utrecht</b>' } ],
    useClusters: true,
    clusterOptions: { gridSize: 60 }
  },
  bubbles: true
}));

### Dispatching a window-level update

window.dispatchEvent(new CustomEvent('lw-map:update', {
  detail: {
    // omit id to target all instances or provide a specific id
    markers: [ { lat: 52.37, lng: 4.90, label_content: '<i>Amsterdam</i>' } ]
  }
}));

### Emitting via Livewire bus

// Livewire v2 example
if (window.Livewire && typeof window.Livewire.emit === 'function') {
  window.Livewire.emit('lw-map:update', {
    markers: [ { lat: 51.92, lng: 4.48, label_content: 'Rotterdam' } ],
    useClusters: false
  });
}

// Livewire v3 (or when bus exposes .on):
if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
  window.Livewire.dispatch('lw-map:update', {
    markers: [ { lat: 51.44, lng: 5.48, label_content: 'Eindhoven' } ]
  });
}

## Behavior regarding Livewire re-renders

- The map container is wrapped with wire:ignore so Livewire won't touch the DOM inside it on updates.
- The JS initialization guards against multiple initializations per component id.
- As a result, Livewire parent/component updates will not re-render the map instance.

## Customization

- mapOptions: Any options accepted by google.maps.Map (besides center/zoom which are provided as base values and then merged). Example: ['mapTypeId' => 'satellite'].
- clusterOptions: Passed directly into new markerClusterer.MarkerClusterer({ map, markers, ...clusterOptions }).

## Development notes

- Namespaces
  - Service Provider: Sdw\\LivewireMaps\\LivewireMapServiceProvider
  - Component class: Sdw\\LivewireMaps\\Livewire\\LivewireMap (alias: 'livewire-map')
  - View: livewire-maps::livewire-map

- After pulling/adding the package locally, run:
  composer dump-autoload

- No assets to publish. Scripts are loaded from CDNs once per page. Multiple map instances are supported.

## Project Plan (kept in sync)

1. Explore repository structure and dependencies. ✓
2. Create minimal Laravel package skeleton. ✓
3. Implement Livewire component class with props, normalization, and render view. ✓
4. Implement Blade view with Google Maps, MarkerClusterer, and event-driven updates; prevent re-rendering. ✓
5. Provide guidelines with usage, attributes, event schema, and plan. ✓
6. Verify names/paths; document composer dump-autoload. ✓
7. Finalize and tag release. ✓

## FAQ

- Q: How do I target a specific map when multiple are on the page?
  A: Use the domId from the 'lw-map:ready' event and include it as detail.id in your update payload, or dispatch the element-level event directly on that container element.

- Q: Can I pass HTML in label_content?
  A: Yes. The HTML string is rendered in a Google InfoWindow when the marker is clicked.

- Q: What happens if Livewire re-renders the component?
  A: The wire:ignore prevents DOM diffing inside the map container and the JS guards prevent re-initialization.


## Testing (Pest v4)

- This package ships with a minimal Pest test setup using Orchestra Testbench.
- Install dev dependencies (in the package root):
  - composer install
- Run tests:
  - composer test
  - or vendor/bin/pest

Test scaffold:
- tests/Pest.php (boots the TestCase)
- tests/TestCase.php (Orchestra Testbench base)
- tests/Feature/ViewRenderTest.php (ensures the Blade view renders)

## Static Analysis (Larastan / PHPStan)

- Config file: phpstan.neon.dist
- Run analysis:
  - composer analyse
  - or composer stan

You can adjust the level and rules in phpstan.neon.dist as needed.

## Laravel Boost (Developer Experience)

- The package includes laravel/boost as a dev dependency to improve developer experience when working with Laravel projects that consume this package.
- Refer to the Laravel Boost documentation for available features and usage within your application context.
