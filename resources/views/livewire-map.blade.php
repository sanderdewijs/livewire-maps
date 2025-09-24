<div id="{{ $domId }}"
        data-lw-map
        wire:ignore
        style="width: {{ $width }}; height: {{ $height }};"
>
    @if(!empty($mapsPlaceholderImg))
        <style>
            #{{ $domId }} {
			background-image: url('{{ $mapsPlaceholderImg }}');
			background-size: cover;
			background-position: center;
			background-repeat: no-repeat;
		}
        </style>
    @endif
</div>

<script>
	(function() {
		@php $cfgAll = config('livewire-maps', []); @endphp
		var cfg = {
			lat: @json($lat),
			lng: @json($lng),
			zoom: @json($zoom),
			markers: @json($normalizedMarkers ?? []),
			useClusters: @json($useClusters ?? false),
			clusterOptions: @json($clusterOptions ?? []),
			mapOptions: @json($mapOptions ?? []),
			drawType: @json($drawType ?? null),
			autoFitBounds: @json(($cfgAll['auto_fit_bounds'] ?? true) ? true : false),
		};
		var id = @json($domId);
		var initEvent = @json($initEvent ?? ($cfgAll['init_event'] ?? null));

		// Init globale namespace en queue indien nodig
		window.__LW_MAPS = window.__LW_MAPS || { instances: {}, queue: [], ready: false };

		function initWithConfig(config){
			if (typeof window.__LW_MAPS.queueInit === 'function') {
				window.__LW_MAPS.queueInit(id, config);
			} else {
				// Shim: duw in queue; het bundle zal dit later oppakken
				try { window.__LW_MAPS.queue.push({ domId: String(id), config: config }); } catch (_) {}
			}
		}

		if (initEvent) {
			var handler = function(e){
				try {
					var overrides = (e && e.detail && typeof e.detail === 'object') ? e.detail : null;
					var finalCfg = cfg;
					if (overrides) {
						finalCfg = Object.assign({}, cfg, overrides);
					}
					initWithConfig(finalCfg);
				} finally {
					try { window.removeEventListener(initEvent, handler); } catch (_) {}
				}
			};
			window.addEventListener(initEvent, handler, { once: true });
		} else {
			initWithConfig(cfg);
		}
	})();
</script>
