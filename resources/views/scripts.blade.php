{{-- Livewire Maps Scripts Directive --}}
{{-- Usage: place @LwMapsScripts before </body> in your layout --}}

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
    $libraries = $cfg['google_maps_libraries'] ?? 'drawing,geometry';
    $locale = $cfg['locale'] ?? null;
    $assetDriver = $cfg['asset_driver'] ?? 'file';
    $cdnUrl = $cfg['cdn_url'] ?? null;
    $viteEntry = $cfg['vite_entry'] ?? 'resources/js/livewire-maps.js';
    $mixPath = $cfg['mix_path'] ?? '/vendor/livewire-maps/livewire-maps.js';
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

{{-- Load package JS based on configured asset driver --}}
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
