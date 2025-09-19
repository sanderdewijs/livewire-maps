# Lara Livewire Maps

A lightweight Livewire v3 map component for Google Maps. It renders a map, places markers (optionally clustered), and lets users draw a selection (circle or polygon). When a selection is completed, events are dispatched with the markers inside the shape. If no markers are inside, useful selection metadata is returned.

Works out-of-the-box with Laravel 12 and Livewire 3.

## Installation
Install via Composer:

```bash
composer require sanderdewijs/lara-livewire-maps
```


## Requirements
- Google Maps JavaScript API key.
    - Enable the Maps JavaScript API in Google Cloud.
    - Provide the API key to the component (see Properties below) or configure it in your app config if supported.

The component automatically loads the Google Maps JS API with the drawing and geometry libraries and the MarkerClusterer library via CDN.


## Configuration
You can configure package-wide defaults via `config/livewire-maps.php`. To publish the config file into your app, run:

```bash
php artisan vendor:publish --provider="Sdw\\LivewireMaps\\LivewireMapServiceProvider" --tag="livewire-maps-config"
```

You can also use the generic config tag:

```bash
php artisan vendor:publish --tag=config --provider="Sdw\\LivewireMaps\\LivewireMapServiceProvider"
```

After publishing, edit `config/livewire-maps.php`. Supported keys:
- api_key (or set `GOOGLE_MAPS_API_KEY` in your `.env`)
- default_zoom
- default_center.lat, default_center.lng
- default_width, default_height
- use_clusters
- map_options
- cluster_options


## Quick Start
Render a map with a couple of markers:

```blade
@php
    $markers = [
        ['id' => 1, 'lat' => 52.0907, 'lng' => 5.1214, 'label_content' => '<strong>Utrecht</strong>', 'title' => 'Utrecht'],
        ['id' => 2, 'lat' => 52.3676, 'lng' => 4.9041, 'title' => 'Amsterdam'],
    ];
@endphp

<livewire:livewire-map
    :zoom="7"
    :center-lat="52.0907"
    :center-lng="5.1214"
    height="360px"
    :markers="$markers"
/>
```

Start drawing immediately (circle or polygon) by passing the `drawType` property:

```blade
<livewire:livewire-map
    :zoom="7"
    :center-lat="52.0907"
    :center-lng="5.1214"
    :markers="$markers"
    :draw-type="'circle'"
/>
```


## Properties
All properties are optional unless noted. Use as Livewire props on the component tag.

- apiKey: string|null
    - Your Google Maps JS API key. If omitted, the component attempts to read it from config('livewire-maps.api_key') if available.
- zoom: int (default 8)
- centerLat: float (default 0.0)
- centerLng: float (default 0.0)
- width: string (default '100%')
- height: string (default '400px')
- useClusters: bool (default false)
    - Enables MarkerClusterer when true.
- mapOptions: array
    - Merged into the Google Map options object.
- clusterOptions: array
    - Passed to MarkerClusterer to configure algorithm/renderer.
- markers: array
    - List of marker definitions. See Marker shape below.
- drawType: 'circle'|'polygon'|null
    - If provided, the map immediately enters draw mode for that shape.

### Marker shape
You can provide any of the following forms:

- Explicit lat/lng:
    - { id?: mixed, lat: float, lng: float, title?: string, label_content?: string (HTML), icon?: string|object }
- Lat/lng as array: { lat_lng: [lat, lng], ...other fields }
- Lat/lng as string: { lat_lng: 'lat,lng', ...other fields }

Notes:
- id is optional but recommended if you plan to correlate markers in selection results.
- label_content, when present, is shown in an InfoWindow on marker click.
- icon can be a URL string or Google Maps Icon object compatible value.


## Selection Drawing (Circle/Polygon)
The map supports starting draw mode in two ways:

1) Property: pass drawType on first render (see Quick Start).
2) Event: ask the map to enter draw mode later (see Events below: `lw-map:draw`).

When the user completes the shape, the component computes which markers fall inside and dispatches a `selection-complete` event with results.


## Events and Listeners (advanced/optional)
Most users can stick to Livewire dispatches from PHP (recommended). The browser/window events below are optional for advanced integrations or when you need direct JS hooks. The component communicates via three channels:
- DOM element events (dispatched/bubbled from the map element)
- Window custom events
- Livewire client bus (window.Livewire or window.livewire)

### Map is ready
- Name: lw-map:ready
- Where: element event (bubbles)
- Payload: { id: string, map: google.maps.Map }

Example:
```js
window.addEventListener('lw-map:ready', (e) => {
  const { id, map } = e.detail;
  console.log('Map ready', id, map);
});
```

### Update markers (and optionally toggle clustering)
- How to update: dispatch the Livewire event from your PHP component using named arguments (property: value) as supported by Livewire 3.
- Listener signature on the Livewire component: onMapUpdate(array $markers = [], bool $useClusters = false, array $clusterOptions = [], array $center = [])
- Frontend: the Blade view listens for an internal element event `lw-map-internal-update` which the component emits after normalizing data. You should not dispatch this internal event yourself.

Examples (from a Livewire PHP component):
```php
// Update only markers (no clustering)
$this->dispatch('lw-map:update', markers: [
    ['lat' => 52.0907, 'lng' => 5.1214, 'title' => 'Utrecht'],
    ['lat_lng' => '52.3676,4.9041', 'title' => 'Amsterdam'],
]);

// Update markers and enable clustering
$this->dispatch('lw-map:update', markers: [
    ['lat' => 52.0907, 'lng' => 5.1214],
    ['lat' => 52.3676, 'lng' => 4.9041],
], useClusters: true);

// Update markers, enable clustering, and pass cluster options
$this->dispatch('lw-map:update', markers: [
    ['lat' => 52.0907, 'lng' => 5.1214],
    ['lat' => 52.3676, 'lng' => 4.9041],
], useClusters: true, clusterOptions: ['maxZoom' => 14]);
```

Notes:
- Marker shapes are normalized server-side (supports `lat`/`lng`, `lat_lng` array, or `lat_lng` string).
- Do not dispatch `lw-map:update` from the browser; use your Livewire PHP component.

### Enter/exit draw mode
- Name: lw-map:draw
- Where the map listens: element event, window event, and Livewire bus
- Payload fields:
    - id?: string
    - type: 'circle' | 'polygon' | null (null exits draw mode)

Examples (backend PHP and frontend $dispatch):
```php
// Backend (Livewire component): start a circle drawing session
$this->dispatch('lw-map:draw', type: 'circle');

// Switch to polygon
$this->dispatch('lw-map:draw', type: 'polygon');

// Exit draw mode
$this->dispatch('lw-map:draw', type: null);
```

```html
<!-- Frontend (inside your Livewire/Alpine scope): start a circle -->
<button type="button" x-on:click="$dispatch('lw-map:draw', { type: 'circle' })">Circle</button>

<!-- Switch to polygon -->
<button type="button" x-on:click="$dispatch('lw-map:draw', { type: 'polygon' })">Polygon</button>

<!-- Exit draw mode -->
<button type="button" x-on:click="$dispatch('lw-map:draw', { type: null })">Exit</button>
```

### Selection complete
Dispatched after the user completes a shape. Emitted on the element, window, and Livewire client bus.

- Name: lw-map:selection-complete
- Payload:
    - id: string (map instance DOM id)
    - type: 'circle' | 'polygon'
    - markers: array of the provided marker objects that are inside the shape
    - center?: { lat: number, lng: number }
    - bounds?: { north: number, east: number, south: number, west: number }
    - radius?: number (meters, present when type === 'circle' and no markers are inside)
    - polygonPath?: string (toString() of polygon.getPath().getArray(), present when type === 'polygon' and no markers are inside)

Example listener:
```js
window.addEventListener('lw-map:selection-complete', (e) => {
  const payload = e.detail;
  console.log('Selection complete:', payload);

  // Example: when no markers selected
  if ((payload.markers || []).length === 0) {
    if (payload.type === 'circle') {
      console.log('Circle center:', payload.center, 'radius(m):', payload.radius);
    } else if (payload.type === 'polygon') {
      console.log('Polygon path:', payload.polygonPath);
    }
  }
});
```

### Selection behavior and use cases
When a user finishes drawing a circle or polygon, the map emits a `lw-map:selection-complete` event. The intended follow-up action is inferred from whether the selection contains any of your markers.

#### Scenario 1: Area query (no markers captured)
- Intent: You likely want to query your own data store for items inside the selected area.
- What you get:
  - `type`: `circle` or `polygon`
  - `markers`: [] (empty)
  - For circles: `center` (lat/lng), `bounds` (north/east/south/west), and `radius` (meters)
  - For polygons: `polygonPath` (stringified path) and `bounds`
- Typical next step: Use the `center` + `radius` (circle) or `polygonPath` (polygon) to run a geoquery in your database.

Example payload (circle, no markers):
```json
{
  "id": "lw-map-123",
  "type": "circle",
  "markers": [],
  "center": { "lat": 52.0907, "lng": 5.1214 },
  "bounds": { "north": 52.2, "east": 5.3, "south": 52.0, "west": 5.0 },
  "radius": 1500
}
```

Example payload (polygon, no markers):
```json
{
  "id": "lw-map-123",
  "type": "polygon",
  "markers": [],
  "bounds": { "north": 52.2, "east": 5.3, "south": 52.0, "west": 5.0 },
  "polygonPath": "(52.10,5.10),(52.15,5.10),(52.15,5.20),(52.10,5.20)"
}
```

#### Scenario 2: Marker selection (one or more markers captured)
- Intent: You likely want to act on the selected markers (e.g., bulk actions, filtering, linking to records).
- What you get:
  - `type`: `circle` or `polygon`
  - `markers`: An array of your original marker objects that fall inside the shape
    - Include an `id` with each marker you provide so you can easily identify selected items on the backend.
- Typical next step: Extract the `id`s from `markers` and pass them to your server or trigger UI actions.

Example payload (markers selected):
```json
{
  "id": "lw-map-123",
  "type": "polygon",
  "markers": [
    { "id": 1, "lat": 52.0907, "lng": 5.1214, "title": "Utrecht" },
    { "id": 2, "lat": 52.3676, "lng": 4.9041, "title": "Amsterdam" }
  ]
}
```

#### Tips
- Always include an `id` in your marker definitions if you plan to use marker selection.
- Differentiate your handling based on whether `markers.length` is zero:
  - `0` → treat as an area/geoquery
  - `> 0` → treat as a marker selection
- See the “Selection complete” event section for the full payload reference and a sample event listener.

## Using with Livewire (recommended)
From a Livewire component, you can update markers or toggle clustering by dispatching the 'lw-map:update' event using named arguments (property: value):

```php
// app/Livewire/Example.php
namespace App\Livewire;

use Livewire\Component;

class Example extends Component
{
    public function addMarkers(): void
    {
        $markers = [
            ['id' => 1, 'lat' => 52.0907, 'lng' => 5.1214, 'title' => 'Utrecht'],
            ['id' => 2, 'lat' => 52.3676, 'lng' => 4.9041, 'title' => 'Amsterdam'],
        ];

        // Update markers (no clustering)
        $this->dispatch('lw-map:update', markers: $markers);

        // Or, update markers and enable clustering with options
        $this->dispatch('lw-map:update', markers: $markers, useClusters: true, clusterOptions: []);
    }

    public function render()
    {
        return view('livewire.example');
    }
}
```

```blade
<!-- resources/views/livewire/example.blade.php -->
<div>
    <button type="button" wire:click="addMarkers">Add markers</button>

    <livewire:livewire-map :zoom="7" :center-lat="52.0907" :center-lng="5.1214" height="360px" />
</div>
```


### Set or update the map center via Livewire
You can recenter the map by including a center in your Livewire dispatch for 'lw-map:update'. You can pass center as an associative array with lat/lng or as separate centerLat/centerLng values.

Examples:

```php
// Update center (and keep your markers): re-send your current markers to avoid clearing them
$this->dispatch('lw-map:update',
    markers: $markers, // your current markers
    center: ['lat' => 52.0907, 'lng' => 5.1214],
);

// Or using separate values
$this->dispatch('lw-map:update',
    markers: $markers, // your current markers
    centerLat: 52.0907,
    centerLng: 5.1214,
);

// Combine with clustering options if desired
$this->dispatch('lw-map:update',
    markers: $markers,
    useClusters: true,
    clusterOptions: ['maxZoom' => 14],
    center: ['lat' => 52.0907, 'lng' => 5.1214],
);
```

Note: The update event replaces the marker list with what you send. If you only want to change the center, re-send your current markers as shown above.

### Start/stop draw mode from Livewire
You can also control draw mode from your Livewire component using the same dispatch API:

```php
// Start drawing a circle
$this->dispatch('lw-map:draw', type: 'circle');

// Switch to polygon
$this->dispatch('lw-map:draw', type: 'polygon');

// Exit draw mode
$this->dispatch('lw-map:draw', type: null);
```

## Marker Clustering
When `useClusters` is true (at render time or via an update event), markers will be grouped using @googlemaps/markerclusterer loaded from a CDN. You can pass `clusterOptions` both at render and in update events.


## Multiple Map Instances
Every map instance gets a unique DOM id (exposed in events as `id`).
- Draw events: when dispatching `lw-map:draw` from the browser, include the `id` to target a specific map; otherwise, all instances may react.
- Marker updates: when you dispatch `lw-map:update` from PHP, each Livewire component updates its own instance; no browser `id` is needed.


## Notes
- Drawing uses Google Maps DrawingManager and Geometry library. The API script is loaded once and shared across instances.
- The component exposes an element-level `lw-map:ready` event right after initialization so you can capture the map instance if needed.
- Selection inclusion checks use geometry.spherical distance for circles and geometry.poly.containsLocation for polygons.
