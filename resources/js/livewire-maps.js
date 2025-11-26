(function () {
    window.__LW_MAPS = window.__LW_MAPS || {
        instances: {},
        queue: [],
        pendingUpdates: {}, // Queue for updates that arrive before map is initialized
        ready: false,
    };
    const LW = window.__LW_MAPS;
    LW.instances = LW.instances || {};
    LW.pendingUpdates = LW.pendingUpdates || {};

    // Debug mode - set to true to enable console logging
    const DEBUG = true;
    function debug(...args) {
        if (DEBUG) console.log('[LW-MAPS]', ...args);
    }

    // === HULPFUNCTIES (grotendeels hetzelfde als voorheen) ===
    function isDisplayed(el) {
        if (!el) return false;
        if (el.offsetWidth > 0 || el.offsetHeight > 0) return true;
        if (el.getClientRects) {
            const rects = el.getClientRects();
            for (let i = 0; rects && i < rects.length; i++) {
                if (rects[i].width > 0 && rects[i].height > 0) {
                    return true;
                }
            }
        }
        if (typeof window.getComputedStyle === 'function') {
            const style = window.getComputedStyle(el);
            if (!style) return false;
            if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity || '1') === 0) {
                return false;
            }
        }
        return false;
    }

    function scheduleQueueRun(delay) { setTimeout(processQueue, delay); }

    function clearMarkers(inst) {
        if (!inst || !inst.leafletMap) return;
        // Do NOT clear drawnItems here - preserve drawn shapes (circles/polygons)
        if (inst.clusterer) {
            inst.leafletMap.removeLayer(inst.clusterer);
            inst.clusterer = null;
        }
        if (inst.markers) inst.markers.forEach(m => inst.leafletMap.removeLayer(m));
        inst.markers = [];
    }

    function setMarkers(inst, markers, useClusters) {
        clearMarkers(inst);
        const leafletMarkers = [];
        const useClusterGroup = useClusters && typeof L.markerClusterGroup === 'function';
        let cluster = null;

        if (useClusterGroup) {
            cluster = L.markerClusterGroup();
        }

        markers.forEach(m => {
            const lat = Number(m.lat), lng = Number(m.lng);
            if (isNaN(lat) || isNaN(lng)) return;

            const markerOptions = {};
            if (m.title) markerOptions.title = m.title;
            if (m.icon) markerOptions.icon = L.icon({ iconUrl: m.icon, iconSize: [25, 41], iconAnchor: [12, 41] });

            const marker = L.marker([lat, lng], markerOptions);

            if (m.label_content) {
                marker.bindPopup(m.label_content);
            }
            marker.userData = m;
            leafletMarkers.push(marker);

            // Voeg marker toe aan cluster OF direct aan map (niet beide!)
            if (useClusterGroup) {
                cluster.addLayer(marker);
            } else {
                marker.addTo(inst.leafletMap);
            }
        });

        inst.markers = leafletMarkers;

        if (useClusterGroup) {
            inst.leafletMap.addLayer(cluster);
            inst.clusterer = cluster;
        }
    }

    // === LEAFLET INITIALISATIE ===
    function initOne(domId, cfg) {
        cfg = cfg || {};
        cfg.enableDrawing = cfg.enableDrawing === '1' || cfg.enableDrawing === true;

        const el = document.getElementById(domId);
        if (!el || !isDisplayed(el)) return false;
        if (LW.instances[domId]) return true;

        const lat = Number(cfg?.lat) || 52.37;
        const lng = Number(cfg?.lng) || 4.90;
        const zoom = Number(cfg?.zoom) || 10;

        // Leaflet map met OpenStreetMap tiles (Google tiles vereisen API key)
        const map = L.map(el, { center: [lat, lng], zoom });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Layer voor getekende features
        const drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        // Draw control (hidden - using custom toolbar instead)
        // We still need the control for programmatic drawing, but hide the UI
        const drawControl = new L.Control.Draw({
            position: 'topleft',
            draw: {
                polygon: true,
                rectangle: false,
                circle: true,
                marker: false,
                polyline: false,
                circlemarker: false
            },
            edit: {
                featureGroup: drawnItems,
                remove: true,
                edit: {
                    selectedPathOptions: {
                        moveMarkers: true
                    }
                }
            }
        });
        map.addControl(drawControl);

        // Hide the default Leaflet draw controls (using custom controls instead)
        setTimeout(() => {
            const drawToolbar = el.querySelector('.leaflet-draw');
            if (drawToolbar) drawToolbar.style.display = 'none';
        }, 0);

        // Toolbar knoppen activeren (jouw bestaande HTML knoppen)
        const toolbar = document.getElementById(`terra-toolbar-${domId}`);
        if (toolbar) {
            if (cfg.enableDrawing) toolbar.style.display = 'flex';

            // Handle draw mode buttons (polygon, circle)
            toolbar.querySelectorAll('.lw-terra-btn[data-mode]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const mode = btn.dataset.mode;
                    toolbar.querySelectorAll('.lw-terra-btn[data-mode]').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');

                    // Simuleer klik op Leaflet.Draw knop
                    const drawType = mode === 'circle' ? 'circle' : 'polygon';
                    setTimeout(() => {
                        const drawButton = document.querySelector(`.leaflet-draw-draw-${drawType}`);
                        if (drawButton) drawButton.click();
                    }, 100);
                });
            });

            // Handle clear/trash button (data-action="clear")
            toolbar.querySelectorAll('.lw-terra-btn[data-action="clear"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    drawnItems.clearLayers();
                    // Clear radius circle reference
                    if (inst.radiusCircle) {
                        inst.radiusCircle = null;
                    }
                    toolbar.querySelectorAll('.lw-terra-btn[data-mode]').forEach(b => b.classList.remove('active'));
                    dispatchComplete(inst);
                });
            });
        }

        const inst = {
            id: domId,
            leafletMap: map,
            drawnItems: drawnItems,
            markers: [],
            config: cfg || {},
            dispatchComplete: null, // Will be set below
        };
        LW.instances[domId] = inst;

        // === EVENT LISTENERS (vervang TerraDraw events) ===
        function dispatchComplete(instance) {
            const layers = [];
            instance.drawnItems.eachLayer(l => layers.push(l));

            if (layers.length === 0) {
                window.Livewire.dispatch('lw-map:draw-complete', { payload: { id: domId, type: null } });
                return;
            }

            const layer = layers[layers.length - 1]; // Laatst getekende
            let payload = { id: domId };

            if (layer instanceof L.Circle) {
                const center = layer.getLatLng();
                const radius = layer.getRadius(); // in meters!
                payload.type = 'circle';
                payload.center = { lat: center.lat, lng: center.lng };
                payload.radius = Math.round(radius);
            } else if (layer instanceof L.Polygon) {
                const path = layer.getLatLngs()[0].map(p => ({ lat: p.lat, lng: p.lng }));
                payload.type = 'polygon';
                payload.polygon = { path };
            }

            // Optioneel: markers binnen shape
            payload.markers = instance.markers
                .filter(m => layer.getBounds?.().contains(m.getLatLng()))
                .map(m => m.userData || {});

            debug('dispatchComplete:', payload);
            window.Livewire.dispatch('lw-map:draw-complete', { payload });
        }

        // Store dispatchComplete on instance so it can be called from handleMapUpdate
        inst.dispatchComplete = dispatchComplete;

        // Na tekenen of bewerken
        map.on(L.Draw.Event.CREATED, e => {
            debug('CREATED event - new shape drawn:', e.layerType);
            const layer = e.layer;
            drawnItems.clearLayers(); // Zorg voor slechts 1 shape
            // Clear radius circle reference since drawnItems was cleared
            if (inst.radiusCircle) {
                debug('Clearing existing radius circle');
                inst.radiusCircle = null;
            }
            drawnItems.addLayer(layer);
            if (layer.editing) {
                layer.editing.enable(); // Maak direct editable: resizable en movable (leaflet-draw-drag handles dragging)
                // Add class to move marker (center handle) to distinguish from resize handles
                setTimeout(() => {
                    if (layer.editing._moveMarker) {
                        const moveIcon = layer.editing._moveMarker._icon;
                        if (moveIcon) {
                            moveIcon.classList.add('leaflet-edit-move');
                        }
                    }
                }, 50);
            }
            // Listen for edit events on the layer to dispatch updates on resize/move
            layer.on('edit', () => {
                debug('Layer edit event - shape resized/moved');
                dispatchComplete(inst);
            });
            dispatchComplete(inst);
        });

        map.on(L.Draw.Event.EDITED, () => dispatchComplete(inst));
        map.on(L.Draw.Event.DELETED, () => dispatchComplete(inst));

        // Init markers
        if (Array.isArray(cfg?.markers)) {
            setMarkers(inst, cfg.markers, !!cfg.useClusters);
        }

        // Process any pending updates that arrived before initialization
        if (LW.pendingUpdates[domId]) {
            debug('Processing pending update for:', domId);
            const pendingData = LW.pendingUpdates[domId];
            delete LW.pendingUpdates[domId];
            // Use setTimeout to ensure the map is fully ready
            setTimeout(() => handleMapUpdate(pendingData), 50);
        }

        return true;
    }

    // === QUEUE & LIVEWIRE HOOKS (bijna identiek als voorheen) ===
    function processQueue() {
        if (!LW.queue.length) return;
        LW.queue = LW.queue.filter(item => {
            try { return !initOne(item.domId, item.config); } catch (_) { return true; }
        });
        if (LW.queue.length) scheduleQueueRun(250);
    }

    LW.queueInit = function (domId, config) {
        const entry = { domId: String(domId), config: config || {} };
        const existing = LW.queue.findIndex(i => i.domId === entry.domId);
        if (existing === -1) LW.queue.push(entry);
        else LW.queue[existing] = entry;

        if (initOne(entry.domId, entry.config)) {
            LW.queue = LW.queue.filter(i => i.domId !== entry.domId);
        }
        scheduleQueueRun(0);
    };

    // Livewire updates (markers, drawType, etc.)
    // Livewire 3 dispatch events worden ontvangen via Livewire.on(), niet window events
    function handleMapUpdate(data) {
        debug('handleMapUpdate received:', data);
        // Livewire 3 stuurt data als array met eerste element als object
        const d = Array.isArray(data) ? data[0] : (data || {});
        const inst = LW.instances[d.id];
        if (!inst) {
            debug('No instance found for id:', d.id, '- queueing update for later');
            // Queue the update for when the map is initialized
            LW.pendingUpdates[d.id] = d;
            // Try to initialize the map now if the DOM element exists
            const el = document.getElementById(d.id);
            if (el) {
                debug('DOM element found, attempting to initialize map:', d.id);
                LW.queueInit(d.id, {
                    lat: el.dataset.lat,
                    lng: el.dataset.lng,
                    zoom: el.dataset.zoom,
                    markers: JSON.parse(el.dataset.markers || '[]'),
                    useClusters: el.dataset.useClusters === 'true' || el.dataset.useClusters === '1',
                    enableDrawing: el.dataset.enableDrawing === '1',
                });
            }
            return;
        }

        if (Array.isArray(d.markers)) {
            debug('Setting markers:', d.markers.length, 'useClusters:', d.useClusters);
            setMarkers(inst, d.markers, !!d.useClusters);
        }
        if (typeof d.zoom === 'number') {
            debug('Setting zoom:', d.zoom);
            inst.leafletMap.setZoom(d.zoom);
        }
        if (d.centerLat && d.centerLng) {
            debug('Setting center:', [d.centerLat, d.centerLng]);
            inst.leafletMap.setView([d.centerLat, d.centerLng]);
        }

        // Handle radius circle
        if (typeof d.radius === 'number' && d.centerLat && d.centerLng) {
            debug('Creating radius circle:', { center: [d.centerLat, d.centerLng], radius: d.radius, options: d.radiusOptions });
            // Clear any existing drawn items and radius circle
            inst.drawnItems.clearLayers();
            if (inst.radiusCircle) {
                inst.leafletMap.removeLayer(inst.radiusCircle);
                inst.radiusCircle = null;
            }
            // Default options for the radius circle
            const defaultOptions = {
                color: '#3388ff',
                fillColor: '#3388ff',
                fillOpacity: 0.2,
                weight: 2,
            };
            const options = { ...defaultOptions, ...(d.radiusOptions || {}) };
            const circle = L.circle([d.centerLat, d.centerLng], {
                radius: d.radius,
                ...options,
            });
            // Add to drawnItems so it becomes editable
            inst.drawnItems.addLayer(circle);
            inst.radiusCircle = circle;
            // Enable editing (resize/move)
            if (circle.editing) {
                circle.editing.enable();
                debug('Radius circle editing enabled');
            }
            // Listen for edit events to dispatch updates
            circle.on('edit', () => {
                debug('Radius circle edit event - resized/moved');
                if (inst.dispatchComplete) {
                    inst.dispatchComplete(inst);
                }
            });
        } else if (d.radius === null && inst.radiusCircle) {
            debug('Removing radius circle (radius=null)');
            // Explicitly remove radius circle when radius is null
            inst.drawnItems.removeLayer(inst.radiusCircle);
            inst.radiusCircle = null;
        }

        // Keep toolbar visible if there are drawn shapes
        if (inst.drawnItems && inst.drawnItems.getLayers().length > 0) {
            const toolbar = document.getElementById(`terra-toolbar-${d.id}`);
            if (toolbar) {
                toolbar.style.display = 'flex';
                debug('Toolbar kept visible - shapes present');
            }
        }
    }

    // Registreer Livewire event listener wanneer Livewire beschikbaar is
    function registerLivewireListeners() {
        if (window.Livewire) {
            window.Livewire.on('lw-map-internal-update', handleMapUpdate);
        }
    }

    // Probeer direct te registreren, of wacht op Livewire init
    if (window.Livewire) {
        registerLivewireListeners();
    } else {
        document.addEventListener('livewire:init', registerLivewireListeners);
    }

    // Fallback: ook luisteren naar native window events (voor backwards compatibility)
    window.addEventListener('lw-map-internal-update', e => {
        handleMapUpdate(e.detail);
    });

    // Listen for draw events
    window.addEventListener('lw-map:draw', e => {
        const d = e.detail || {};
        const type = d.type || null;
        const targetIds = d.id ? [String(d.id)] : Object.keys(LW.instances);
        targetIds.forEach(mapId => {
            const inst = LW.instances[mapId];
            if (!inst) return;
            // Toon toolbar altijd bij draw event
            const toolbar = document.getElementById(`terra-toolbar-${mapId}`);
            if (toolbar) toolbar.style.display = 'flex';
            if (!type) {
                // Clear
                inst.drawnItems.clearLayers();
                // Clear radius circle reference
                if (inst.radiusCircle) {
                    inst.radiusCircle = null;
                }
                // dispatch clear event
                window.Livewire.dispatch('lw-map:draw-complete', { payload: { id: mapId, type: null } });
            } else {
                // Activeer mode
                const drawType = type === 'circle' ? 'circle' : 'polygon';
                setTimeout(() => {
                    const drawButton = document.querySelector(`.leaflet-draw-draw-${drawType}`);
                    if (drawButton) drawButton.click();
                }, 100);
            }
        });
    });

    // Auto-init bestaande maps
    function initMissingMaps() {
        document.querySelectorAll('[data-lw-map]').forEach(el => {
            const id = el.id;
            if (!id || LW.instances[id]) return;
            const cfg = {
                lat: el.dataset.lat,
                lng: el.dataset.lng,
                zoom: el.dataset.zoom,
                markers: JSON.parse(el.dataset.markers || '[]'),
                useClusters: el.dataset.useClusters === 'true',
                enableDrawing: el.dataset.enableDrawing === '1',
            };
            LW.queueInit(id, cfg);
        });
    }

    if (document.readyState === 'complete') initMissingMaps();
    else document.addEventListener('DOMContentLoaded', initMissingMaps);

    try { processQueue(); } catch (_) {}
})();
