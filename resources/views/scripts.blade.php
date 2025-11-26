{{-- Livewire Maps Scripts Directive --}}
{{-- Usage: place @LwMapsScripts before </body> in your layout --}}

{{-- Leaflet CSS --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

{{-- Bootstrap global namespace to avoid inline duplicates in components --}}
<script>
	window.__LW_MAPS = window.__LW_MAPS || { instances: {}, queue: [], ready: false };

        (function() {
                var LW = window.__LW_MAPS;

                // 1) Shim die items pusht vóór het bundle geladen is
                if (typeof LW.queueInit !== 'function' || LW.queueInit.__isShim === true) {
                        var shim = function(domId, config) {
                                try { LW.queue.push({ domId: String(domId), config: config || {} }); } catch (_) {}
                        };
                        shim.__isShim = true;
                        LW.__queueInitShim = shim;
                        LW.queueInit = shim;
                }

                // 2) Poller die wacht op de échte queueInit uit het bundle en daarna de bestaande queue drained
                if (!LW.__bootDrainPoller) {
                        LW.__bootDrainPoller = setInterval(function() {
                                try {
                                        // Als het bundle geladen is, zal LW.queueInit opnieuw gedefinieerd zijn (niet onze shim)
                                        var current = LW.queueInit;
                                        var shimRef = LW.__queueInitShim;
                                        var isReal = (typeof current === 'function')
                                                && current.__isShim !== true
                                                && (!shimRef || current !== shimRef);

                                        if (isReal) {
                                                // Drain bestaande queue via de echte queueInit zodat processQueue/timers worden geactiveerd
                                                var items = LW.queue.slice();
                                                LW.queue = [];
                                                items.forEach(function(item){
                                                        try { LW.queueInit(item.domId, item.config); } catch(_) {}
                                                });

                                                clearInterval(LW.__bootDrainPoller);
                                                LW.__bootDrainPoller = null;
                                        }
                                } catch(_) {}
                        }, 50);
                }
        })();
</script>

@php
    $cfg = config('livewire-maps', []);
    $loadGoogle = (bool) ($cfg['load_google_maps'] ?? true);
    $googleKey = $cfg['google_maps_key'] ?? ($cfg['api_key'] ?? null);
    $libraries = $cfg['google_maps_libraries'] ?? 'geometry';
    $locale = $cfg['locale'] ?? null;
    $assetDriver = $cfg['asset_driver'] ?? 'file';
    $cdnUrl = $cfg['cdn_url'] ?? null;
    $viteEntry = $cfg['vite_entry'] ?? 'resources/js/livewire-maps.js';
    $mixPath = $cfg['mix_path'] ?? '/vendor/livewire-maps/livewire-maps.js';
    $clustererSrc = 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js';
    $loadsClusterer = $assetDriver !== 'none';
@endphp

{{-- Optionally include Google Maps API --}}
@if($loadGoogle && $googleKey)
    @php
        $gmSrc = 'https://maps.googleapis.com/maps/api/js?key=' . urlencode($googleKey);
        if ($libraries) { $gmSrc .= '&libraries=' . urlencode($libraries); }
        if ($locale) { $gmSrc .= '&language=' . urlencode($locale); }
    @endphp
    <script id="lw-google-maps-script" src="{{ $gmSrc }}" async defer></script>
@endif

{{-- Leaflet JS --}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

{{-- Leaflet MarkerCluster (must load after Leaflet) --}}
@if($loadsClusterer)
    <script src="{{ $clustererSrc }}"></script>
@endif
<script src="https://cdn.jsdelivr.net/npm/leaflet.path.drag@0.0.3/src/Path.Drag.min.js"></script>
{{-- Polyfill for L.DomEvent methods removed in Leaflet 1.8+ --}}
<script>
if (typeof L !== 'undefined' && L.DomEvent) {
    if (!L.DomEvent.fakeStop) {
        L.DomEvent.fakeStop = function() { return true; };
    }
    if (!L.DomEvent.skipped) {
        L.DomEvent.skipped = function() { return true; };
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/leaflet-draw-drag@0.4.8/dist/Leaflet.draw.drag-src.min.js"></script>


@switch($assetDriver)
    @case('vite')
        @vite($viteEntry)
        @break

    @case('mix')
        <script src="{{ mix($mixPath) }}" defer></script>
        @break

    @case('cdn')
        @if($cdnUrl)
            <script src="{{ $cdnUrl }}" defer></script>
        @endif
        @break

    @case('file')
        <script src="{{ asset('vendor/livewire-maps/livewire-maps.js') }}" defer></script>
        @break

    @case('none')
        {{-- Intentionally do not load any JS --}}
        @break

    @default
        <script src="{{ asset('vendor/livewire-maps/livewire-maps.js') }}" defer></script>
@endswitch
