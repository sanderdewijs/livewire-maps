<?php

namespace Sdw\LivewireMaps\Livewire;

use Livewire\Component;

class LivewireMap extends Component
{
    public $apiKey;
    public $zoom = 8;
    public $centerLat = 0.0;
    public $centerLng = 0.0;
    public $width = '100%';
    public $height = '400px';
    public $useClusters = false;
    public $mapOptions = [];
    public $clusterOptions = [];
    public $markers = [];
    public $domId;
    public $drawType = null; // 'circle' | 'polygon' | null

    /**
     * Listen for map update events from Livewire.
     * We'll normalize incoming markers and update the internal state.
     *
     * Using Livewire's event system ensures data flows through PHP so marker
     * shapes like `lat_lng` strings/arrays get normalized consistently.
     */
    protected $listeners = [
        'lw-map:update' => 'onMapUpdate',
    ];

    public function mount(
        $apiKey = null,
        $zoom = null,
        $centerLat = null,
        $centerLng = null,
        $width = null,
        $height = null,
        $useClusters = null,
        $mapOptions = null,
        $clusterOptions = null,
        $markers = [],
        $drawType = null
    ): void {
        // Read defaults from config
        $cfg = config('livewire-maps');

        $this->apiKey = $apiKey ?? ($cfg['api_key'] ?? null);

        $defaultZoom = is_numeric($cfg['default_zoom'] ?? null) ? (int) $cfg['default_zoom'] : 8;
        $this->zoom = is_numeric($zoom) ? (int) $zoom : $defaultZoom;

        $defaultCenterLat = isset($cfg['default_center']['lat']) && is_numeric($cfg['default_center']['lat']) ? (float) $cfg['default_center']['lat'] : 0.0;
        $defaultCenterLng = isset($cfg['default_center']['lng']) && is_numeric($cfg['default_center']['lng']) ? (float) $cfg['default_center']['lng'] : 0.0;
        $this->centerLat = is_numeric($centerLat) ? (float) $centerLat : $defaultCenterLat;
        $this->centerLng = is_numeric($centerLng) ? (float) $centerLng : $defaultCenterLng;

        $this->width = $width ?? ($cfg['default_width'] ?? '100%');
        $this->height = $height ?? ($cfg['default_height'] ?? '400px');

        $defaultUseClusters = (bool) ($cfg['use_clusters'] ?? false);
        $this->useClusters = is_bool($useClusters) ? $useClusters : $defaultUseClusters;

        $this->mapOptions = is_array($mapOptions) ? $mapOptions : (is_array($cfg['map_options'] ?? null) ? $cfg['map_options'] : []);
        $this->clusterOptions = is_array($clusterOptions) ? $clusterOptions : (is_array($cfg['cluster_options'] ?? null) ? $cfg['cluster_options'] : []);

        $this->markers = is_array($markers) ? $this->normalizeMarkers($markers) : [];
        $this->drawType = $drawType; // can be null, 'circle', or 'polygon'

        // Prefer Livewire component id if available for a stable DOM id across re-renders
        $this->domId = 'lw-map-' . (property_exists($this, 'id') ? $this->id : substr(md5(spl_object_hash($this)), 0, 8));
    }

    /**
     * Normalize and update markers when the map update event is received.
     *
     * @param array $payload { id?: string, markers?: array, useClusters?: bool, clusterOptions?: array }
     */
    public function onMapUpdate(array $payload = []): void
    {
        $incoming = isset($payload['markers']) && is_array($payload['markers']) ? $payload['markers'] : [];

        // Normalize and update component state
        $this->markers = $this->normalizeMarkers($incoming);

        // Optionally allow toggling clusters via event
        if (array_key_exists('useClusters', $payload)) {
            $this->useClusters = (bool) $payload['useClusters'];
        }
        if (isset($payload['clusterOptions']) && is_array($payload['clusterOptions'])) {
            $this->clusterOptions = $payload['clusterOptions'];
        }

        // Dispatch an update back to the frontend via Livewire bus with normalized markers
        // Use an internal event name so external listeners can still use 'lw-map:update' for input
        $this->dispatch('lw-map:internal:update', [
            'id' => $this->domId,
            'markers' => $this->markers,
            'useClusters' => $this->useClusters,
            'clusterOptions' => $this->clusterOptions,
        ]);
    }

    protected function normalizeMarkers($markers): array
    {
        $out = [];
        foreach ($markers as $m) {
            $lat = null; $lng = null;
            if (isset($m['lat_lng'])) {
                if (is_string($m['lat_lng']) && strpos($m['lat_lng'], ',') !== false) {
                    $parts = explode(',', $m['lat_lng'], 2);
                    $latStr = trim($parts[0]);
                    $lngStr = isset($parts[1]) ? trim($parts[1]) : '0';
                    $lat = (float) $latStr; $lng = (float) $lngStr;
                } elseif (is_array($m['lat_lng']) && isset($m['lat_lng'][0], $m['lat_lng'][1])) {
                    $lat = (float) $m['lat_lng'][0];
                    $lng = (float) $m['lat_lng'][1];
                }
            } else {
                if (isset($m['lat'])) { $lat = (float) $m['lat']; }
                if (isset($m['lng'])) { $lng = (float) $m['lng']; }
            }

            if ($lat === null || $lng === null) {
                continue;
            }

            $out[] = [
                'id' => $m['id'] ?? null,
                'lat' => $lat,
                'lng' => $lng,
                'label_content' => isset($m['label_content']) ? $m['label_content'] : null,
                'icon' => isset($m['icon']) ? $m['icon'] : null,
                'title' => isset($m['title']) ? $m['title'] : null,
            ];
        }
        return $out;
    }

    public function render()
    {
        return view('livewire-maps::livewire-map', [
            'normalizedMarkers' => $this->markers,
            'domId' => $this->domId,
            'drawType' => $this->drawType,
        ]);
    }
}
