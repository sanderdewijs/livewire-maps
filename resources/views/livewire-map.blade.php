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
        var src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(apiKey);
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
        if (useClusters) {
            ensureClusterer(function() {
                instance.clusterer = new markerClusterer.MarkerClusterer(Object.assign({ map: instance.map, markers: markers }, clusterOptions || {}));
            });
        }
    }

    function init() {
        var el = document.getElementById(domId);
        if (!el) return;
        if (state.instances[domId]) return; // already initialized

        ensureGoogleMaps(initial.apiKey, function() {
            var mapOptions = Object.assign({ center: initial.center, zoom: initial.zoom }, initial.mapOptions || {});
            var map = new google.maps.Map(el, mapOptions);
            var instance = { map: map, markers: [], clusterer: null };
            state.instances[domId] = instance;

            updateMarkers(instance, initial.markers, initial.useClusters, initial.clusterOptions);

            // Expose an API for external control
            el.dispatchEvent(new CustomEvent('lw-map:ready', { detail: { id: domId, map: map }, bubbles: true }));

            // Listen for updates: element-level
            el.addEventListener('lw-map:update', function(e) {
                var d = e.detail || {};
                if (d.id && d.id !== domId) return;
                updateMarkers(instance, d.markers || [], typeof d.useClusters === 'boolean' ? d.useClusters : initial.useClusters, d.clusterOptions || initial.clusterOptions);
            });

            // Window-level custom event
            window.addEventListener('lw-map:update', function(e) {
                var d = (e && e.detail) || {};
                if (!d || (d.id && d.id !== domId)) return;
                updateMarkers(instance, d.markers || [], typeof d.useClusters === 'boolean' ? d.useClusters : initial.useClusters, d.clusterOptions || initial.clusterOptions);
            });

            // Livewire bus (v2 or v3 safe)
            function subscribeLivewireBus() {
                try {
                    var bus = window.Livewire || (window.livewire && window.livewire);
                    if (bus && typeof bus.on === 'function') {
                        bus.on('lw-map:update', function(d) {
                            if (!d || (d.id && d.id !== domId)) return;
                            updateMarkers(instance, d.markers || [], typeof d.useClusters === 'boolean' ? d.useClusters : initial.useClusters, d.clusterOptions || initial.clusterOptions);
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
