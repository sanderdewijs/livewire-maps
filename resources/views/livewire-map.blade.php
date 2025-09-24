<div id="{{ $domId }}"
     data-lw-map
     @if($drawType) data-draw-type="{{ $drawType }}" @endif
     data-lat="{{ $lat }}"
     data-lng="{{ $lng }}"
     data-zoom="{{ $zoom }}"
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
