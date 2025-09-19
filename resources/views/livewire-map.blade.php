<div
        id="{{ $domId }}"
        data-lw-map
        wire:ignore
        style="width: {{ $width }}; height: {{ $height }};"
></div>

<script>
	(function() {
		var cfg = {
			lat: @json($lat),
			lng: @json($lng),
			zoom: @json($zoom),
			markers: @json($normalizedMarkers ?? []),
			useClusters: @json($useClusters ?? false),
			clusterOptions: @json($clusterOptions ?? []),
			mapOptions: @json($mapOptions ?? []),
			drawType: @json($drawType ?? null),
		};
		var id = @json($domId);

		// Init globale namespace en queue indien nodig
		window.__LW_MAPS = window.__LW_MAPS || { instances: {}, queue: [], ready: false };
		if (typeof window.__LW_MAPS.queueInit === 'function') {
			window.__LW_MAPS.queueInit(id, cfg);
		} else {
			// Shim: duw in queue; het bundle zal dit later oppakken
			try { window.__LW_MAPS.queue.push({ domId: String(id), config: cfg }); } catch (_) {}
		}
	})();
</script>
