# Lara Livewire Maps

A lightweight Livewire v3 map component for Google Maps. It renders a map, places markers (optionally clustered), and lets users draw a selection (circle or polygon). When a selection is completed, events are dispatched with the markers inside the shape. If no markers are inside, useful selection metadata is returned.

Works out-of-the-box with Laravel 12 and Livewire 3.


## Requirements
- Google Maps JavaScript API key.
    - Enable the Maps JavaScript API in Google Cloud.
    - Provide the API key to the component (see Properties below) or configure it in your app config if supported.

The component automatically loads the Google Maps JS API with the drawing and geometry libraries and the MarkerClusterer library via CDN.


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


## Events and Listeners
The component communicates via three channels for flexibility:
- DOM element events (dispatched/bubbled from the map element)
- Window custom events
- Livewire client bus (window.Livewire or window.livewire)

You can listen on whichever channel fits your integration.

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
- Name: lw-map:update
- Where the map listens: element event, window event, and Livewire bus
- Payload shape (required): the event MUST include a `payload` property containing the update options. No exceptions.
  - payload: {
    - id?: string (target a specific map instance)
    - markers?: array (marker list as described above)
    - useClusters?: boolean
    - clusterOptions?: object
  }

Examples (trigger updates):
```js
// Window event (recommended)
window.dispatchEvent(new CustomEvent('lw-map:update', {
  detail: { payload: { id: 'lw-map-123', markers: [...], useClusters: true } }
}));

// Livewire v3 client bus (from the browser)
(window.Livewire || window.livewire)?.dispatch('lw-map:update', {
  payload: {
    id: 'lw-map-123',
    markers: [...],
  }
});
```

Note: The frontend does not normalize update payloads. All normalization happens in the Livewire component. Always send the nested `payload` structure.

### Enter/exit draw mode
- Name: lw-map:draw
- Where the map listens: element event, window event, and Livewire bus
- Payload fields:
    - id?: string
    - type: 'circle' | 'polygon' | null (null exits draw mode)

Examples:
```js
// Start a circle drawing session
window.dispatchEvent(new CustomEvent('lw-map:draw', {
  detail: { id: 'lw-map-123', type: 'circle' }
}));

// Switch to polygon
window.dispatchEvent(new CustomEvent('lw-map:draw', {
  detail: { id: 'lw-map-123', type: 'polygon' }
}));

// Exit draw mode
window.dispatchEvent(new CustomEvent('lw-map:draw', {
  detail: { id: 'lw-map-123', type: null }
}));
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


## Using with Livewire actions
From a Livewire component, you can dispatch to the client bus to update markers or toggle draw mode. For example:

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

        $this->dispatch('lw-map:update', markers: $markers);
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


## Marker Clustering
When `useClusters` is true (at render time or via an update event), markers will be grouped using @googlemaps/markerclusterer loaded from a CDN. You can pass `clusterOptions` both at render and in update events.


## Multiple Map Instances
Every map instance gets a unique DOM id (exposed in events as `id`). When sending update or draw events, include the `id` to target a specific instance. If omitted, all instances listening at the window or bus level will receive the payload; each instance filters by `id` when provided.


## Notes
- Drawing uses Google Maps DrawingManager and Geometry library. The API script is loaded once and shared across instances.
- The component exposes an element-level `lw-map:ready` event right after initialization so you can capture the map instance if needed.
- Selection inclusion checks use geometry.spherical distance for circles and geometry.poly.containsLocation for polygons.
