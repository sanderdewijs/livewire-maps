<div style="position: relative; width: {{ $width }}; height: {{ $height }};">
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
         data-enable-drawing="{{ config('livewire-maps.enable_drawing', false) ? '1' : '0' }}"
         wire:ignore
         style="width: 100%; height: 100%;"
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

    <div id="terra-toolbar-{{ $domId }}" class="lw-terra-toolbar" style="display: none;">
        <button class="lw-terra-btn" data-mode="polygon" title="Polygon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Polygon shape: 4 punten verbonden met lijnen -->
                <path d="M5 5L19 8L17 19L3 14Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" fill="none"/>
                <!-- Punten op de hoeken -->
                <circle cx="5" cy="5" r="2" fill="currentColor"/>
                <circle cx="19" cy="8" r="2" fill="currentColor"/>
                <circle cx="17" cy="19" r="2" fill="currentColor"/>
                <circle cx="3" cy="14" r="2" fill="currentColor"/>
            </svg>
        </button>
        <button class="lw-terra-btn" data-mode="circle" title="Circle">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
            </svg>
        </button>
        <button class="lw-terra-btn lw-terra-trash" data-action="clear" title="Clear">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 6H21M8 6V4C8 3.44772 8.44772 3 9 3H15C15.5523 3 16 3.44772 16 4V6M19 6V20C19 20.5523 18.5523 21 18 21H6C5.44772 21 5 20.5523 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>

    <style>
        .lw-terra-toolbar {
            position: absolute;
            top: 10px;
            right: 10px;
            background: white;
            padding: 8px;
            border-radius: 4px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .lw-terra-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: white;
            cursor: pointer;
            border-radius: 2px;
            transition: background 0.2s;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lw-terra-btn:hover {
            background: #f0f0f0;
        }

        .lw-terra-btn.active {
            background: #e0e0e0;
        }

        .lw-terra-trash {
            background: #000;
            color: white;
        }

        .lw-terra-trash:hover {
            background: #333;
        }

        .lw-terra-trash:active {
            background: #555;
        }

        /* Leaflet.Draw edit handles: subtielere ronde cirkels met lichtblauwe vulkleur */
        .leaflet-editing-icon {
            width: 12px !important;
            height: 12px !important;
            margin-left: -6px !important;
            margin-top: -6px !important;
            background: #87CEEB !important;
            border: 2px solid #4A90D9 !important;
            border-radius: 50% !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2) !important;
        }

        /* Midpoint handles (voor het toevoegen van nieuwe punten) */
        .leaflet-div-icon {
            background: rgba(135, 206, 235, 0.6) !important;
            border: 1px solid #4A90D9 !important;
            border-radius: 50% !important;
            width: 10px !important;
            height: 10px !important;
            margin-left: -5px !important;
            margin-top: -5px !important;
        }

        /* Circle resize handle (rand van cirkel) */
        .leaflet-marker-icon.leaflet-editing-icon {
            width: 12px !important;
            height: 12px !important;
            background: #87CEEB !important;
            border: 2px solid #4A90D9 !important;
            border-radius: 50% !important;
        }

        /* Move marker (center punt voor verplaatsen) - iets groter en andere kleur */
        .leaflet-marker-icon.leaflet-edit-move {
            width: 14px !important;
            height: 14px !important;
            margin-left: -7px !important;
            margin-top: -7px !important;
            background: #FFD700 !important;
            border: 2px solid #DAA520 !important;
            border-radius: 50% !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3) !important;
        }

        /* Resize cursor voor edit handles (rand) bij hover/drag - horizontale dubbele pijl */
        /* Excludeer de move marker via :not(.leaflet-edit-move) */
        .leaflet-editing-icon:not(.leaflet-edit-move):hover,
        .leaflet-editing-icon:not(.leaflet-edit-move):active,
        .leaflet-marker-dragging .leaflet-editing-icon:not(.leaflet-edit-move) {
            cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24'%3E%3Cpath d='M4 12L8 8M4 12L8 16M4 12H20M20 12L16 8M20 12L16 16' stroke='%23333' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") 12 12, ew-resize !important;
        }

        /* Move cursor voor center/move marker - 4-way move icoon */
        .leaflet-edit-move:hover,
        .leaflet-edit-move:active,
        .leaflet-marker-dragging .leaflet-edit-move,
        .leaflet-edit-move.leaflet-marker-dragging {
            cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24'%3E%3Cpath d='M12 2L15 5H13V11H19V9L22 12L19 15V13H13V19H15L12 22L9 19H11V13H5V15L2 12L5 9V11H11V5H9L12 2Z' fill='%23333'/%3E%3C/svg%3E") 12 12, move !important;
        }

        /* Polygon vertex handles - move cursor */
        .leaflet-div-icon:hover,
        .leaflet-div-icon:active {
            cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24'%3E%3Cpath d='M12 2L15 5H13V11H19V9L22 12L19 15V13H13V19H15L12 22L9 19H11V13H5V15L2 12L5 9V11H11V5H9L12 2Z' fill='%23333'/%3E%3C/svg%3E") 12 12, move !important;
        }
    </style>
</div>
