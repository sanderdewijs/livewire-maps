<div id="{{ $domId }}" wire:ignore style="width: {{ $width }}; height: {{ $height }};"></div>

<script>
    (function() {
        // ---- Globale state --------------------------------------------------------
        window.__LW_MAPS = window.__LW_MAPS || {
            instances: {},
            apiScriptId: 'lw-google-maps-script',
            clustererScriptId: 'lw-markerclusterer-script',
            apiLoaded: false, apiLoading: false,
            clustererLoaded: false, clustererLoading: false
        };
        var state = window.__LW_MAPS;

        // ---- Config uit Blade -----------------------------------------------------
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

        // ---- Helpers --------------------------------------------------------------
        function dbg(label, extra) {
            try { console.log('[lw-map] ' + label, extra ?? ''); } catch(_) {}
        }

        function loadScriptOnce(id, src, onload) {
            var el = document.getElementById(id);
            if (el) {
                if (onload) {
                    if (id === state.apiScriptId && state.apiLoaded) onload();
                    else if (id === state.clustererScriptId && state.clustererLoaded) onload();
                    else el.addEventListener('load', onload);
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
            // Versie pinnen om API‑wijzigingen te voorkomen
            var src = 'https://unpkg.com/@googlemaps/markerclusterer@2.5.3/dist/index.min.js';
            loadScriptOnce(state.clustererScriptId, src, function() {
                state.clustererLoaded = true; state.clustererLoading = false; cb();
            });
        }

        // ---- Markers & Clusters ---------------------------------------------------
        // attachToMap = true  → markers direct aan map hangen (geen cluster)
        // attachToMap = false → markers NIET aan map hangen (clusterer beheert ze)
        function createMarkers(map, markerData, attachToMap) {
            var markers = [];
            for (var i = 0; i < (markerData || []).length; i++) {
                var d = markerData[i];
                var opts = {
                    position: { lat: Number(d.lat), lng: Number(d.lng) },
                    title: d.title || undefined,
                    icon: d.icon || undefined,
                };
                if (attachToMap) opts.map = map;
                var m = new google.maps.Marker(opts);

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

            // a) Ruim álle bekende clusterers op
            if (instance._clusterers && instance._clusterers.length) {
                instance._clusterers.forEach(function(cl) {
                    try { cl.setMarkers && cl.setMarkers([]); } catch(_) {}
                    try { cl.clearMarkers && cl.clearMarkers(); } catch(_) {}
                    try { cl.setMap && cl.setMap(null); } catch(_) {}
                    try { cl.repaint && cl.repaint(); } catch(_) {}
                });
            }
            instance._clusterers = [];
            instance.clusterer = null;

            // b) Koppel losse markers los van de map
            if (instance.markers && instance.markers.length) {
                for (var i = 0; i < instance.markers.length; i++) {
                    try { instance.markers[i].setMap(null); } catch(_) {}
                }
            }
            instance.markers = [];
            instance.markerData = [];

            // c) Zachte repaint om eventuele overlay‑resten te flushen
            try {
                var z = instance.map.getZoom();
                instance.map.setZoom(z + 1);
                instance.map.setZoom(z);
            } catch(_) {}
        }

        function updateMarkers(instance, markerData, useClusters, clusterOptions) {
            instance._callCount = (instance._callCount || 0) + 1;
            clearMarkers(instance);

            // Sequence‑guard: alleen laatste update mag clusterer aanmaken
            instance._seq = (instance._seq || 0) + 1;
            var seq = instance._seq;

            var attachToMap = !useClusters;
            var markers = createMarkers(instance.map, markerData || [], attachToMap);
            instance.markers = markers;
            instance.markerData = markerData || [];

            if (!useClusters) return;

            ensureClusterer(function() {
                // Negeer verouderde callbacks (race condition fix)
                if (seq !== instance._seq) { return; }

                instance._clusterers = instance._clusterers || [];
                var cl = new markerClusterer.MarkerClusterer(Object.assign({
                    map: instance.map,
                    markers: markers
                }, clusterOptions || {}));
                instance._clusterers.push(cl);
                instance.clusterer = cl;
            });
        }

        // ---- Selectie / tekenen ---------------------------------------------------
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

                    // Meta (center/bounds/radius/polygonPath)
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
                            if (!included.length) { try { meta.radius = e.overlay.getRadius(); } catch(_) {} }
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
                            if (!included.length && path && typeof path.getArray === 'function') {
                                try {
                                    var coordinates = path.getArray();
                                    meta.polygonPath = coordinates.toString();
                                } catch(_) {}
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

        // ---- De‑dupe & centrale update handler -----------------------------------
        function handleUpdate(instance, d) {
            if (!d) return;
            if (d.id && d.id !== domId) return;

            // Burst de‑dupe binnen 25ms
            var now = (window.performance && performance.now) ? performance.now() : Date.now();
            if (instance._lastUpdateAt && (now - instance._lastUpdateAt) < 25) {
                return;
            }
            instance._lastUpdateAt = now;

            // Payload‑hash de‑dupe (identieke updates in zelfde tick)
            try {
                var hash = JSON.stringify({
                    markers: d.markers || [],
                    useClusters: (typeof d.useClusters === 'boolean') ? d.useClusters : initial.useClusters,
                    clusterOptions: d.clusterOptions || initial.clusterOptions
                });
                if (instance._lastPayloadHash === hash) {
                    return;
                }
                instance._lastPayloadHash = hash;
            } catch(_) {}

            updateMarkers(
                instance,
                d.markers || [],
                (typeof d.useClusters === 'boolean') ? d.useClusters : initial.useClusters,
                d.clusterOptions || initial.clusterOptions
            );
        }

        // ---- Init -----------------------------------------------------------------
        function init() {
            var el = document.getElementById(domId);
            if (!el) return;
            if (state.instances[domId]) return; // al geïnitialiseerd

            ensureGoogleMaps(initial.apiKey, function() {
                // (optioneel) clusterer alvast preloaden als initial.useClusters true is
                if (initial.useClusters) ensureClusterer(function(){});

                var mapOptions = Object.assign({ center: initial.center, zoom: initial.zoom }, initial.mapOptions || {});
                var map = new google.maps.Map(el, mapOptions);
                var instance = {
                    map: map, markers: [], markerData: [],
                    clusterer: null, _clusterers: [],
                    drawingManager: null, drawnShape: null,
                    _seq: 0, _callCount: 0
                };
                state.instances[domId] = instance;

                updateMarkers(instance, initial.markers, initial.useClusters, initial.clusterOptions);

                // Start drawing indien aangeleverd
                if (initial.drawType) startDrawing(instance, initial.drawType);

                // API voor external control
                try {
                    el.dispatchEvent(new CustomEvent('lw-map:ready', { detail: { id: domId, map: map }, bubbles: true }));
                } catch(_) {}

                // ---- Luister alleen op element‑niveau (voorkomt dubbele events)
                el.addEventListener('lw-map-internal-update', function(e) {
                    handleUpdate(instance, (e && e.detail) || {});
                });
                el.addEventListener('lw-map:draw', function(e) {
                    var d = (e && e.detail) || {};
                    if (d.id && d.id !== domId) return;
                    startDrawing(instance, d.type || null);
                });

                // ---- (Optioneel) Livewire bus — UIT laten als je element‑events gebruikt
                function subscribeLivewireBus() {
                    try {
                        var bus = window.Livewire || (window.livewire && window.livewire);
                        if (bus && typeof bus.on === 'function') {
                            // Kies óf element‑events óf Livewire (niet beide). Laat deze uit als element‑events actief zijn.
                            // bus.on('lw-map-internal-update', function(d) { handleUpdate(instance, d || {}); });
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
