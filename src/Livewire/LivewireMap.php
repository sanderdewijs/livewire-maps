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

    public function mount(
        $apiKey = null,
        $zoom = 8,
        $centerLat = null,
        $centerLng = null,
        $width = '100%',
        $height = '400px',
        $useClusters = false,
        $mapOptions = [],
        $clusterOptions = [],
        $markers = []
    ) {
        $this->apiKey = $apiKey;
        $this->zoom = is_numeric($zoom) ? (int)$zoom : 8;
        $this->centerLat = is_numeric($centerLat) ? (float)$centerLat : 0.0;
        $this->centerLng = is_numeric($centerLng) ? (float)$centerLng : 0.0;
        $this->width = $width;
        $this->height = $height;
        $this->useClusters = (bool)$useClusters;
        $this->mapOptions = is_array($mapOptions) ? $mapOptions : [];
        $this->clusterOptions = is_array($clusterOptions) ? $clusterOptions : [];
        $this->markers = is_array($markers) ? $this->normalizeMarkers($markers) : [];
        // Prefer Livewire component id if available for a stable DOM id across re-renders
        $this->domId = 'lw-map-' . (property_exists($this, 'id') ? $this->id : substr(md5(spl_object_hash($this)), 0, 8));
    }

    protected function normalizeMarkers($markers)
    {
        $out = [];
        foreach ($markers as $m) {
            $lat = null; $lng = null;
            if (isset($m['lat_lng'])) {
                if (is_string($m['lat_lng']) && strpos($m['lat_lng'], ',') !== false) {
                    $parts = explode(',', $m['lat_lng'], 2);
                    $latStr = trim($parts[0]);
                    $lngStr = isset($parts[1]) ? trim($parts[1]) : '0';
                    $lat = (float)$latStr; $lng = (float)$lngStr;
                } elseif (is_array($m['lat_lng']) && isset($m['lat_lng'][0], $m['lat_lng'][1])) {
                    $lat = (float)$m['lat_lng'][0];
                    $lng = (float)$m['lat_lng'][1];
                }
            } else {
                if (isset($m['lat'])) { $lat = (float)$m['lat']; }
                if (isset($m['lng'])) { $lng = (float)$m['lng']; }
            }

            if ($lat === null || $lng === null) {
                continue;
            }

            $out[] = [
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
        ]);
    }
}
