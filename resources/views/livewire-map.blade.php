<div
  id="{{ $domId }}"
  data-lw-map
  wire:ignore
  style="width: {{ $width }}; height: {{ $height }};"
></div>

<script>
  if (window.__LW_MAPS && window.__LW_MAPS.queueInit) {
    window.__LW_MAPS.queueInit(@json($domId), {
      lat: @json($lat),
      lng: @json($lng),
      zoom: @json($zoom),
      // Initial state moved to external JS: markers, clustering, options, drawing
      markers: @json($normalizedMarkers ?? []),
      useClusters: @json($useClusters ?? false),
      clusterOptions: @json($clusterOptions ?? []),
      mapOptions: @json($mapOptions ?? []),
      drawType: @json($drawType ?? null),
    });
  }
</script>
