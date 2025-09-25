<div id="{{ $domId }}"
     data-lw-map
     @if($drawType) data-draw-type="{{ $drawType }}" @endif
     data-lat="{{ $lat }}"
     data-lng="{{ $lng }}"
     data-zoom="{{ $zoom }}"
     data-use-clusters="{{ $useClusters ? '1' : '0' }}"
     data-cluster-options='@json($clusterOptions)'
     data-map-options='@json($mapOptions)'
     data-markers='@json($normalizedMarkers)'
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
