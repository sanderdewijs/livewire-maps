# Lara Livewire Maps

A lightweight Livewire v3 map component for Google Maps. It renders a map, places markers (optionally clustered), and lets users draw a selection (circle or polygon). When a selection is completed, events are dispatched with the markers inside the shape. If no markers are inside, useful selection metadata is returned.

Works out-of-the-box with Laravel 12 and Livewire 3.

## Installation
Install via Composer:

```bash
composer require sanderdewijs/lara-livewire-maps
```

### Add the scripts directive to your layout (important)
This package ships a Blade include directive that loads the required JavaScript for the map and (optionally) the Google Maps API. Place the directive once per page, ideally immediately after the opening <body> tag in your main layout.

Example layout:

```blade
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My App</title>
    @vite(['resources/js/app.js'])
    @livewireStyles
</head>
<body>
    {{ $slot ?? '' }}

    @livewireScripts
    @LwMapsScripts
</body>
</html>
```

Notes:
- Use the directive only once per page.
- By default, the directive will load the package JS from public/vendor (see Asset loading below) and will also include the Google Maps JS API when you provide an API key.


## Requirements
- Google Maps JavaScript API key.
    - Enable the Maps JavaScript API in Google Cloud.
    - Provide the API key to the component (see Properties below) or configure it in your app config if supported.

The @LwMapsScripts directive loads the Google Maps JS API (drawing and geometry libraries) and the MarkerClusterer library. You can control this behavior via config (see below).


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
- google_maps_key (preferred; or set `LW_MAPS_GOOGLE_KEY` in your `.env`)
- load_google_maps (bool, default true) — set false if you load Google Maps yourself
- google_maps_libraries (default `drawing,geometry`)
- locale (Google Maps UI language, e.g. `nl`, `en`)
- default_zoom
- default_center.lat, default_center.lng
- default_width, default_height
- use_clusters
- auto_fit_bounds (bool, default true; or set `LW_MAPS_AUTO_FIT_BOUNDS=false` to disable)
- map_options
- cluster_options
- init_event (string|null): when set, the map waits for this browser event before initializing
- maps_placeholder_img: string|null — optional background image URL used as a placeholder before the map initializes. Use in combination with the init_event property to display a placeholder when map initialization is deferred.
- asset_driver: `vite` | `mix` | `cdn` | `file` | `none` (default `file`)
- cdn_url (when asset_driver = `cdn`)
- vite_entry (when asset_driver = `vite`, default `resources/js/livewire-maps.js`)
- mix_path (when asset_driver = `mix`, default `/vendor/livewire-maps/livewire-maps.js`)

### Asset loading options (Vite, Mix, CDN, file)
By default, `asset_driver` is `file`, which expects the package JS to be published to `public/vendor`. The `@LwMapsScripts` directive will then include it automatically.

- File (default, no bundler):
  1) Publish the JS once:
  ```bash
  php artisan vendor:publish --provider="Sdw\\LivewireMaps\\LivewireMapServiceProvider" --tag=livewire-maps-assets
  # or
  php artisan vendor:publish --provider="Sdw\\LivewireMaps\\LivewireMapServiceProvider" --tag=public
  ```
  2) Keep `asset_driver` as `file` (default). The directive will include `/vendor/livewire-maps/livewire-maps.js`.
  3) Re-publish or overwrite later using the dedicated command:
  ```bash
  # publish (creates the directory if missing)
  php artisan livewire-maps:publish-assets
  
  # force overwrite if the file already exists
  php artisan livewire-maps:publish-assets --force
  ```
  This command copies the package asset from `resources/js/livewire-maps.js` to `public/vendor/livewire-maps/livewire-maps.js`.

- Vite:
  1) In `.env` or config, set `LW_MAPS_ASSET_DRIVER=vite` (or `asset_driver` => 'vite').
  2) Ensure you have a Vite entry that imports the package script. For example, create `resources/js/livewire-maps.js` in your app with:
  ```js
  // resources/js/livewire-maps.js
  import '../../vendor/sanderdewijs/lara-livewire-maps/resources/js/livewire-maps.js';
  ```
  3) Add it to your Vite inputs (vite.config.js):
  ```js
  laravel({
    input: ['resources/js/app.js', 'resources/js/livewire-maps.js'],
    refresh: true,
  })
  ```
  4) Optionally adjust `vite_entry` in `config/livewire-maps.php` if you use a different path.

- Laravel Mix:
  1) In `.env` or config, set `LW_MAPS_ASSET_DRIVER=mix` (or `asset_driver` => 'mix').
  2) Create `resources/js/livewire-maps.js` that imports the package script (same import as Vite example).
  3) In `webpack.mix.js`:
  ```js
  mix.js('resources/js/livewire-maps.js', 'public/vendor/livewire-maps').version();
  ```
  4) Ensure `mix_path` in config matches `/vendor/livewire-maps/livewire-maps.js`.

- CDN:
  1) Host the compiled package JS yourself and set `asset_driver` to `cdn`.
  2) Set `cdn_url` to the full URL.

- None (advanced):
  - Set `asset_driver` to `none` if you want to fully control script loading yourself. In this mode, `@LwMapsScripts` will not include any JS; you must load both the package JS and (optionally) the Google Maps API on your own.

Google Maps loading:
- The directive loads Google Maps when `load_google_maps` is true and a key is present (`google_maps_key` or `api_key`).
- You can disable it by setting `load_google_maps=false` if you prefer to include the Google script tag elsewhere.


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
- initEvent: string|null
    - Optional. When set, the map will wait for this browser event to be dispatched before initializing. Overrides the `init_event` config if both are set.
- mapsPlaceholderImg: string|null
    - Optional URL. When set (via prop or config `maps_placeholder_img`), shows a background image covering the container until the map initializes.

### Delayed initialization via custom event
Sometimes you want the map to initialize only after other backend work has completed. You can configure a custom event name globally or per component and dispatch it when you're ready.

- Globally (config): set `init_event` in `config/livewire-maps.php` or `.env` `LW_MAPS_INIT_EVENT=my-app:maps:init`
- Per component: pass the prop
  ```blade
  <livewire:livewire-map :init-event="'my-app:maps:init'" />
  ```

When you're ready to initialize (e.g., after backend work completes), dispatch a Livewire/Alpine event with the same name as `init_event`.

Frontend (Alpine/Livewire in your Blade):
```html
<!-- Ensure your map component has initEvent or config init_event set to 'my-app:maps:init' -->
<button type="button" x-on:click="$dispatch('my-app:maps:init')">Init map</button>
```

You can include overrides in the payload (all keys optional). These will be shallow-merged into the initial config:
```html
<button type="button"
        x-on:click="$dispatch('my-app:maps:init', {
            lat: 52.09,
            lng: 5.12,
            zoom: 8,
            markers: [ { id: 1, lat: 52.09, lng: 5.12 } ],
            useClusters: true,
            clusterOptions: { maxZoom: 14 },
            mapOptions: { disableDefaultUI: true },
            drawType: 'circle',
            autoFitBounds: false,
        })">
    Init with overrides
</button>
```

Backend (Livewire PHP component):
```php
// From your Livewire component when data is ready
$this->dispatch('my-app:maps:init',
    lat: 52.09,
    lng: 5.12,
    zoom: 8,
    markers: [ [ 'id' => 1, 'lat' => 52.09, 'lng' => 5.12 ] ],
    useClusters: true,
    clusterOptions: [ 'maxZoom' => 14 ],
    mapOptions: [ 'disableDefaultUI' => true ],
    drawType: 'circle',
    autoFitBounds: false,
);
```

Notes:
- If `init_event` is null (default), the map initializes immediately on render (current behavior).
- If both config and prop are set, the prop takes precedence for that component instance.

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
This package initially had support for custom browser events, but this will change to only Livewire event support for simplicity.

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
Dispatched after the user completes a shape. Emitted on the Livewire client bus.

- Name: lw-map:draw-complete (Livewire event)
- Payload object shape:
    - id: string (map instance DOM id)
    - type: 'circle' | 'polygon'
    - circle?: { center: { lat: number, lng: number }, radius: number }
    - polygon?: { path: Array<{ lat: number, lng: number }> }

Example listener (Livewire v3 client bus):
```js
Livewire.on('lw-map:draw-complete', ({ payload }) => {
  console.log('Draw complete:', payload);

  if (payload.type === 'circle' && payload.circle) {
    const { center, radius } = payload.circle;
    console.log('Circle center:', center, 'radius(m):', radius);
  }

  if (payload.type === 'polygon' && payload.polygon) {
    console.log('Polygon path:', payload.polygon.path);
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
