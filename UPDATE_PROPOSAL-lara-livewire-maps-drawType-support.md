# Update proposal: Runtime drawType support via `lw-map:update` (Solution 1)

This proposal adds first-class support for toggling the Google Maps drawing tools (circle / polygon) at runtime by passing a `drawType` parameter through the existing `lw-map:update` Livewire event. The frontend JS in the package already supports a `drawType` key on the internal browser event (`lw-map-internal-update`), but the PHP component does not currently forward it.

With this change, applications can enable drawing tools in a single round-trip without timing issues, e.g. immediately after geocoding an address.

## Summary
- Extend `Sdw\LivewireMaps\Livewire\LivewireMap::onMapUpdate()` to accept an optional `?string $drawType` parameter.
- When provided, include `drawType` in the payload of the dispatched `lw-map-internal-update` browser event so the JS enables/updates the drawing tools immediately.
- No changes needed in JS or Blade; they already support `drawType` on the internal event and initial render.
- Backwards compatible: apps not sending `drawType` are unaffected.

## Unified diff
Patch is relative to the package root.

```diff
diff --git a/src/Livewire/LivewireMap.php b/src/Livewire/LivewireMap.php
index 3c7f1aa..d2b7e55 100644
--- a/src/Livewire/LivewireMap.php
+++ b/src/Livewire/LivewireMap.php
@@ -95,7 +95,7 @@ class LivewireMap extends Component
     /**
      * Normalize and update markers when the map update event is received.
      */
-    public function onMapUpdate(array $markers = [], bool $useClusters = false, array $clusterOptions = [], array $center = [], ?int $zoom = null): void
+    public function onMapUpdate(array $markers = [], bool $useClusters = false, array $clusterOptions = [], array $center = [], ?int $zoom = null, ?string $drawType = null): void
     {
         //Normalize and update component state
         $this->markers = $this->normalizeMarkers($markers);
@@ -116,39 +116,88 @@ class LivewireMap extends Component
         // Dispatch an update back to the frontend via Livewire bus with normalized markers
         // Use an internal event name so external listeners can still use 'lw-map:update' for input
-        if ($center && is_int($zoom)) {
-            $this->dispatch('lw-map-internal-update',
-                id: $this->domId,
-                markers: $this->markers,
-                useClusters: $this->useClusters,
-                clusterOptions: $this->clusterOptions,
-                centerLat: $this->centerLat,
-                centerLng: $this->centerLng,
-                zoom: $this->zoom,
-            );
-        } elseif ($center) {
-            $this->dispatch('lw-map-internal-update',
-                id: $this->domId,
-                markers: $this->markers,
-                useClusters: $this->useClusters,
-                clusterOptions: $this->clusterOptions,
-                centerLat: $this->centerLat,
-                centerLng: $this->centerLng,
-            );
-        } elseif (is_int($zoom)) {
-            $this->dispatch('lw-map-internal-update',
-                id: $this->domId,
-                markers: $this->markers,
-                useClusters: $this->useClusters,
-                clusterOptions: $this->clusterOptions,
-                zoom: $this->zoom,
-            );
-        } else {
-            $this->dispatch('lw-map-internal-update',
-                id: $this->domId,
-                markers: $this->markers,
-                useClusters: $this->useClusters,
-                clusterOptions: $this->clusterOptions,
-            );
-        }
+        if ($center && is_int($zoom)) {
+            if ($drawType !== null) {
+                $this->dispatch('lw-map-internal-update',
+                    id: $this->domId,
+                    markers: $this->markers,
+                    useClusters: $this->useClusters,
+                    clusterOptions: $this->clusterOptions,
+                    centerLat: $this->centerLat,
+                    centerLng: $this->centerLng,
+                    zoom: $this->zoom,
+                    drawType: $drawType,
+                );
+            } else {
+                $this->dispatch('lw-map-internal-update',
+                    id: $this->domId,
+                    markers: $this->markers,
+                    useClusters: $this->useClusters,
+                    clusterOptions: $this->clusterOptions,
+                    centerLat: $this->centerLat,
+                    centerLng: $this->centerLng,
+                    zoom: $this->zoom,
+                );
+            }
+        } elseif ($center) {
+            if ($drawType !== null) {
+                $this->dispatch('lw-map-internal-update',
+                    id: $this->domId,
+                    markers: $this->markers,
+                    useClusters: $this->useClusters,
+                    clusterOptions: $this->clusterOptions,
+                    centerLat: $this->centerLat,
+                    centerLng: $this->centerLng,
+                    drawType: $drawType,
+                );
+            } else {
+                $this->dispatch('lw-map-internal-update',
+                    id: $this->domId,
+                    markers: $this->markers,
+                    useClusters: $this->useClusters,
+                    clusterOptions: $this->clusterOptions,
+                    centerLat: $this->centerLat,
+                    centerLng: $this->centerLng,
+                );
+            }
+        } elseif (is_int($zoom)) {
+            if ($drawType !== null) {
+                $this->dispatch('lw-map-internal-update',
+                    id: $this->domId,
+                    markers: $this->markers,
+                    useClusters: $this->useClusters,
+                    clusterOptions: $this->clusterOptions,
+                    zoom: $this->zoom,
+                    drawType: $drawType,
+                );
+            } else {
+                $this->dispatch('lw-map-internal-update',
+                    id: $this->domId,
+                    markers: $this->markers,
+                    useClusters: $this->useClusters,
+                    clusterOptions: $this->clusterOptions,
+                    zoom: $this->zoom,
+                );
+            }
+        } else {
+            if ($drawType !== null) {
+                $this->dispatch('lw-map-internal-update',
+                    id: $this->domId,
+                    markers: $this->markers,
+                    useClusters: $this->useClusters,
+                    clusterOptions: $this->clusterOptions,
+                    drawType: $drawType,
+                );
+            } else {
+                $this->dispatch('lw-map-internal-update',
+                    id: $this->domId,
+                    markers: $this->markers,
+                    useClusters: $this->useClusters,
+                    clusterOptions: $this->clusterOptions,
+                );
+            }
+        }
     }
```

Notes:
- The change is minimal and isolated: only the PHP component is updated.
- We intentionally do not modify `$this->drawType` so initial render behavior remains unchanged; runtime draw toggling happens via the JS update handler. If desired, you could also synchronize `$this->drawType = $drawType ?? $this->drawType;` for SSR parity on future re-renders.

## Usage example in an app
After applying this change in the package, an app can enable drawing immediately in the same update as center/zoom:

```php
$this->dispatch('lw-map:update',
    markers: [[
        'lat' => (float) $lat,
        'lng' => (float) $lng,
        'label_content' => $formattedAddress ?? null,
    ]],
    useClusters: true,
    center: ['lat' => (float) $lat, 'lng' => (float) $lng],
    zoom: 14,
    drawType: 'circle', // NEW: activates drawing tools directly
)->to(\Sdw\LivewireMaps\Livewire\LivewireMap::class);
```

No extra `lw-map:draw` dispatch is required anymore for the initial activation. Subsequent changes can still use `lw-map:draw` if preferred.

## Backwards compatibility
- Fully backward compatible: existing calls without `drawType` behave exactly the same.
- Frontend JS already supports `drawType` on `lw-map-internal-update` and will ignore it if omitted.

## Testing recommendations
- Feature test: dispatch `lw-map:update` with `drawType: 'circle'` and assert the browser receives an internal update containing `drawType => 'circle'`.
- Browser test (Dusk): verify the drawing toolbar appears and that `lw-map:draw-complete` is emitted after drawing a circle/polygon.

## Implementation notes
- Ensure the Google Maps API is loaded with the `drawing` library (e.g., via `@LwMapsScripts` which includes `drawing,geometry` by default).
- No changes are required to `resources/js/livewire-maps.js` or Blade views.

---
Prepared for: `sanderdewijs/lara-livewire-maps`
Date: 2025-09-29
Author: Buurtmailing.com project update proposal
