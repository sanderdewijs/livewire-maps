<div id="{{ $domId }}" wire:ignore style="width: {{ $width }}; height: {{ $height }};"></div>

<script>
(function() {
    window.__LW_MAPS = window.__LW_MAPS || { instances: {}, apiScriptId: 'lw-google-maps-script', clustererScriptId: 'lw-markerclusterer-script', apiLoaded: false, apiLoading: false, clustererLoaded: false, clustererLoading: false };

    var state = window.__LW_MAPS;
    var domId = @json($domId);
    var initial = {
        apiKey: @json($apiKey),
        zoom: Number(@json($zoom)) || 8,
        center: { lat: Number(@json($centerLat)) || 0, lng: Number(@json($centerLng)) || 0 },
        useClusters: Boolean(@json($useClusters)),
        mapOptions: @json(isset($mapOptions) ? $mapOptions : []),
        clusterOptions: @json(isset($clusterOptions) ? $clusterOptions : []),
        markers: @json(isset($normalizedMarkers) ? $normalizedMarkers : []),
        drawType: @json($drawType ?? null),
    };

    function loadScriptOnce(id, src, onload) {
        if (document.getElementById(id)) {
            if (onload) {
                if (id === state.apiScriptId && state.apiLoaded) onload();
                else if (id === state.clustererScriptId && state.clustererLoaded) onload();
                else document.getElementById(id).addEventListener('load', onload);
            }
            return;
        }
        var s = document.createElement('script');
        s.id = id; s.src = src; s.async = true; s.defer = true;
        if (onload) s.addEventListener('load', onload);
        document.head.appendChild(s);
    }

    function ensureGoogleMaps(apiKey, cb) {
        if (window.google && window.google.maps) return cb();
        if (state.apiLoading) {
            // Wait until loaded
            var check = setInterval(function() {
                if (window.google && window.google.maps) { clearInterval(check); cb(); }
            }, 50);
            return;
        }
        state.apiLoading = true;
        var src = 'https://maps.googleapis.com/maps/api/js?libraries=drawing,geometry&key=' + encodeURIComponent(apiKey);
        loadScriptOnce(state.apiScriptId, src, function() {
            state.apiLoaded = true; state.apiLoading = false; cb();
        });
    }

    function ensureClusterer(cb) {
        if (window.markerClusterer && window.markerClusterer.MarkerClusterer) return cb();
        if (state.clustererLoading) {
            var check = setInterval(function() {
                if (window.markerClusterer && window.markerClusterer.MarkerClusterer) { clearInterval(check); cb(); }
            }, 50);
            return;
        }
        state.clustererLoading = true;
        var src = 'https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js';
        loadScriptOnce(state.clustererScriptId, src, function() {
            state.clustererLoaded = true; state.clustererLoading = false; cb();
        });
    }

    function createMarkers(map, markerData) {
        var markers = [];
        for (var i = 0; i < markerData.length; i++) {
            var d = markerData[i];
            var m = new google.maps.Marker({
                position: { lat: Number(d.lat), lng: Number(d.lng) },
                map: map,
                title: d.title || undefined,
                icon: d.icon || undefined,
            });
            if (d.label_content) {
                (function(marker, html) {
                    var iw = new google.maps.InfoWindow({ content: html });
                    marker.addListener('click', function() { iw.open({ anchor: marker, map: map }); });
                })(m, d.label_content);
            }
            markers.push(m);
        }
        return markers;
    }

    function clearMarkers(instance) {
        if (!instance) return;
        if (instance.clusterer) {
            try { instance.clusterer.clearMarkers(); } catch (e) {}
            instance.clusterer = null;
        }
        if (instance.markers) {
            for (var i = 0; i < instance.markers.length; i++) {
                try { instance.markers[i].setMap(null); } catch (e) {}
            }
            instance.markers = [];
        }
    }

    function updateMarkers(instance, markerData, useClusters, clusterOptions) {
        clearMarkers(instance);
        var markers = createMarkers(instance.map, markerData || []);
        instance.markers = markers;
        instance.markerData = markerData || [];
        if (useClusters) {
            ensureClusterer(function() {
                instance.clusterer = new markerClusterer.MarkerClusterer(Object.assign({ map: instance.map, markers: markers }, clusterOptions || {}));
            });
        }
    }

    function dispatchSelection(instance, type, included, meta) {
        var el = document.getElementById(domId);
        var payload = { id: domId, type: type, markers: included };
        if (meta && typeof meta === 'object') {
            if (meta.center) { payload.center = meta.center; }
            if (meta.bounds) { payload.bounds = meta.bounds; }
            if (typeof meta.radius === 'number') { payload.radius = meta.radius; }
            if (typeof meta.polygonPath === 'string') { payload.polygonPath = meta.polygonPath; }
        }
        try { el.dispatchEvent(new CustomEvent('lw-map:selection-complete', { detail: payload, bubbles: true })); } catch (e) {}
        try { window.dispatchEvent(new CustomEvent('lw-map:selection-complete', { detail: payload })); } catch (e) {}
        try {
            var bus = window.Livewire || (window.livewire && window.livewire);
            if (bus && typeof bus.dispatch === 'function') {
                bus.dispatch('lw-map:selection-complete', payload);
            }
        } catch (e) {}
    }

    function clearShape(instance) {
        if (instance.drawnShape) {
            try { instance.drawnShape.setMap(null); } catch (e) {}
            instance.drawnShape = null;
        }
    }

    function startDrawing(instance, type) {
        if (!window.google || !google.maps || !google.maps.drawing) { return; }
        if (!instance.drawingManager) {
            instance.drawingManager = new google.maps.drawing.DrawingManager({
                drawingMode: null,
                drawingControl: false,
                polygonOptions: { editable: false, draggable: false, fillOpacity: 0.2 },
                circleOptions: { editable: false, draggable: false, fillOpacity: 0.2 },
            });
            instance.drawingManager.setMap(instance.map);

            google.maps.event.addListener(instance.drawingManager, 'overlaycomplete', function(e) {
                clearShape(instance);
                instance.drawnShape = e.overlay;
                instance.drawingManager.setDrawingMode(null);

                // Compute inclusion
                var included = [];
                var data = instance.markerData || [];
                for (var i = 0; i < data.length; i++) {
                    var d = data[i];
                    var pos = new google.maps.LatLng(Number(d.lat), Number(d.lng));
                    var inside = false;
                    if (e.type === 'circle') {
                        var center = e.overlay.getCenter();
                        var radius = e.overlay.getRadius();
                        var dist = google.maps.geometry.spherical.computeDistanceBetween(center, pos);
                        inside = dist <= radius;
                    } else if (e.type === 'polygon') {
                        inside = google.maps.geometry.poly.containsLocation(pos, e.overlay);
                    }
                    if (inside) { included.push(d); }
                }

                // Compute selection meta (center and bounds)
                var meta = {};
                try {
                    if (e.type === 'circle') {
                        var c = e.overlay.getCenter();
                        var b = e.overlay.getBounds && e.overlay.getBounds();
                        meta.center = { lat: c.lat(), lng: c.lng() };
                        if (b) {
                            var ne = b.getNorthEast();
                            var sw = b.getSouthWest();
                            meta.bounds = { north: ne.lat(), east: ne.lng(), south: sw.lat(), west: sw.lng() };
                        }
                        // When no markers are included, add radius explicitly
                        if (!included.length) {
                            try { meta.radius = e.overlay.getRadius(); } catch (_) {}
                        }
                    } else if (e.type === 'polygon') {
                        var path = e.overlay.getPath && e.overlay.getPath();
                        var bounds = new google.maps.LatLngBounds();
                        if (path && typeof path.forEach === 'function') {
                            path.forEach(function(latlng){ bounds.extend(latlng); });
                            var ctr = bounds.getCenter();
                            var ne2 = bounds.getNorthEast();
                            var sw2 = bounds.getSouthWest();
                            meta.center = { lat: ctr.lat(), lng: ctr.lng() };
                            meta.bounds = { north: ne2.lat(), east: ne2.lng(), south: sw2.lat(), west: sw2.lng() };
                        }
                        // When no markers are included, add polygonPath string
                        if (!included.length && path && typeof path.getArray === 'function') {
                            try {
                                var coordinates = path.getArray();
                                meta.polygonPath = coordinates.toString();
                            } catch (_) {}
                        }
                    }
                } catch (err) {}

                dispatchSelection(instance, e.type, included, meta);
            });
        }

        if (type === 'circle') {
            instance.drawingManager.setDrawingMode(google.maps.drawing.OverlayType.CIRCLE);
        } else if (type === 'polygon') {
            instance.drawingManager.setDrawingMode(google.maps.drawing.OverlayType.POLYGON);
        } else {
            instance.drawingManager.setDrawingMode(null);
        }
    }

    function init() {
        var el = document.getElementById(domId);
        if (!el) return;
        if (state.instances[domId]) return; // already initialized

        ensureGoogleMaps(initial.apiKey, function() {
            var mapOptions = Object.assign({ center: initial.center, zoom: initial.zoom }, initial.mapOptions || {});
            var map = new google.maps.Map(el, mapOptions);
            var instance = { map: map, markers: [], markerData: [], clusterer: null, drawingManager: null, drawnShape: null };
            state.instances[domId] = instance;

            updateMarkers(instance, initial.markers, initial.useClusters, initial.clusterOptions);

            // Start drawing if provided initially
            if (initial.drawType) {
                startDrawing(instance, initial.drawType);
            }

            // Expose an API for external control
            el.dispatchEvent(new CustomEvent('lw-map:ready', { detail: { id: domId, map: map }, bubbles: true }));

            // Listen for updates: element-level (draw only)
            el.addEventListener('lw-map:draw', function(e) {
                var d = e.detail || {};
                if (d.id && d.id !== domId) return;
                startDrawing(instance, d.type || null);
            });

            // Window-level custom event (draw only)
            window.addEventListener('lw-map:draw', function(e) {
                var d = (e && e.detail) || {};
                if (!d || (d.id && d.id !== domId)) return;
                startDrawing(instance, d.type || null);
            });

            // Livewire bus (v2 or v3 safe)
            function subscribeLivewireBus() {
                try {
                    var bus = window.Livewire || (window.livewire && window.livewire);
                    if (bus && typeof bus.on === 'function') {
                        bus.on('lw-map:internal:update', function(d) {
                            if (!d || (d.id && d.id !== domId)) return;
                            updateMarkers(instance, d.markers || [], typeof d.useClusters === 'boolean' ? d.useClusters : initial.useClusters, d.clusterOptions || initial.clusterOptions);
                        });
                        bus.on('lw-map:draw', function(d) {
                            if (!d || (d.id && d.id !== domId)) return;
                            startDrawing(instance, d.type || null);
                        });
                    }
                } catch (e) {}
            }
            if (document.readyState === 'complete') subscribeLivewireBus();
            else document.addEventListener('livewire:init', subscribeLivewireBus);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
