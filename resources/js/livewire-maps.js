(function () {
	// Global bootstrap if not already present
	window.__LW_MAPS = window.__LW_MAPS || {
		instances: {},
		queue: [],
		ready: false,
	};
        const LW = window.__LW_MAPS;
        LW.instances = LW.instances || {};
        LW.queue = Array.isArray(LW.queue) ? LW.queue : [];

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

        function scheduleQueueRun(delay) {
                try {
                        setTimeout(processQueue, delay);
                } catch (_) {}
        }

	// Ensure Google Maps API is present before initializing any map
	function ensureGoogle(cb) {
		if (window.google && window.google.maps) return cb();
		// If the Google API is loaded via <script> in the page, wait until it is available
		const int = setInterval(() => {
			if (window.google && window.google.maps) {
				clearInterval(int);
				cb();
			}
		}, 50);
	}

	function clearMarkers(inst) {
		if (!inst) return;
		if (inst.clusterer && typeof inst.clusterer.clearMarkers === 'function') {
			try { inst.clusterer.clearMarkers(); } catch (_) {}
			inst.clusterer = null;
		}
		if (Array.isArray(inst.markers)) {
			inst.markers.forEach(m => { try { m.setMap(null); } catch (_) {} });
		}
		inst.markers = [];
		if (inst.infoWindow) { try { inst.infoWindow.close(); } catch (_) {} }
	}

	function fitToMarkers(inst, markers) {
		try {
			if (!inst || !inst.map || !Array.isArray(markers) || markers.length === 0) { return; }
			if (markers.length === 1) {
				const pos = markers[0] && typeof markers[0].getPosition === 'function' ? markers[0].getPosition() : null;
				if (pos) {
					inst.map.setCenter(pos);
					return;
				}
			}
			const bounds = new google.maps.LatLngBounds();
			markers.forEach(mk => {
				try {
					const p = mk.getPosition ? mk.getPosition() : null;
					if (p) { bounds.extend(p); }
				} catch (_) {}
			});
			inst.map.fitBounds(bounds);
		} catch (_) {}
	}

	function setMarkers(inst, markers, useClusters, clusterOptions) {
		if (!inst || !inst.map || !Array.isArray(markers)) return;
		clearMarkers(inst);

		const gMarkers = [];
		markers.forEach(m => {
			const pos = { lat: Number(m.lat), lng: Number(m.lng) };
			if (isNaN(pos.lat) || isNaN(pos.lng)) return;
			const mk = new google.maps.Marker({
				position: pos,
				map: useClusters ? null : inst.map,
				title: m.title || undefined,
				icon: m.icon || undefined,
			});
			if (m.label_content) {
				inst.infoWindow = inst.infoWindow || new google.maps.InfoWindow();
				mk.addListener('click', () => {
					try {
						inst.infoWindow.setContent(m.label_content);
						inst.infoWindow.open({ anchor: mk, map: inst.map, shouldFocus: false });
					} catch (_) {}
				});
			}
			gMarkers.push(mk);
		});

		inst.markers = gMarkers;

		// Optional clustering with support for both new and legacy globals
		if (useClusters) {
			let MC = null;
			// Newer UMD bundle (@googlemaps/markerclusterer)
			if (window.markerClusterer && typeof window.markerClusterer.MarkerClusterer === 'function') {
				MC = window.markerClusterer.MarkerClusterer;
			} else if (typeof window.MarkerClusterer === 'function') {
				// Legacy global (markerclustererplus or older builds)
				MC = window.MarkerClusterer;
			}

			if (MC) {
				try {
					inst.clusterer = new MC({
						map: inst.map,
						markers: gMarkers,
						...((clusterOptions && typeof clusterOptions === 'object') ? clusterOptions : {}),
					});
					return; // clustering is active, no need for fallback
				} catch (_) {}
			}

			// Fallback: clusterer not available or failed → show markers directly on map
			try { gMarkers.forEach(mk => mk.setMap(inst.map)); } catch (_) {}
		}
	}

	function setupDrawing(inst, drawType) {
		if (!inst || !inst.map || !drawType) return;
		if (!google.maps.drawing || !google.maps.drawing.DrawingManager) return;

		// Clean up any previous drawing manager and overlay
		try {
			if (inst.drawingManager && typeof inst.drawingManager.setMap === 'function') {
				inst.drawingManager.setMap(null);
			}
		} catch (_) {}
		inst.drawingManager = null;
		try {
			if (inst.drawOverlay && typeof inst.drawOverlay.setMap === 'function') {
				inst.drawOverlay.setMap(null);
			}
		} catch (_) {}
		inst.drawOverlay = null;

		// Only allow a single overlay at a time after re-init
		function clearOverlay() {
			if (inst.drawOverlay) {
				try { inst.drawOverlay.setMap(null); } catch (_) {}
				inst.drawOverlay = null;
			}
		}

		const manager = new google.maps.drawing.DrawingManager({
			drawingMode: drawType === 'circle' ? google.maps.drawing.OverlayType.CIRCLE
				: drawType === 'polygon' ? google.maps.drawing.OverlayType.POLYGON
					: null,
			drawingControl: true,
			drawingControlOptions: {
				position: google.maps.ControlPosition.TOP_CENTER,
				drawingModes: [
					...(drawType === 'circle' ? [google.maps.drawing.OverlayType.CIRCLE] : []),
					...(drawType === 'polygon' ? [google.maps.drawing.OverlayType.POLYGON] : []),
				],
			},
			circleOptions: { fillOpacity: 0.2, strokeWeight: 2 },
			polygonOptions: { fillOpacity: 0.2, strokeWeight: 2 },
		});

		manager.setMap(inst.map);
		inst.drawingManager = manager;

		google.maps.event.addListener(manager, 'overlaycomplete', function (evt) {
			clearOverlay();
			inst.drawOverlay = evt.overlay;

			// Build a payload with geometry details
			let payload = { id: inst.id, type: evt.type };
			try {
				if (evt.type === google.maps.drawing.OverlayType.CIRCLE) {
					const center = evt.overlay.getCenter();
					payload.circle = {
						center: { lat: center.lat(), lng: center.lng() },
						radius: evt.overlay.getRadius(),
					};
				} else if (evt.type === google.maps.drawing.OverlayType.POLYGON) {
					const path = evt.overlay.getPath();
					const coords = [];
					for (let i = 0; i < path.getLength(); i++) {
						const p = path.getAt(i);
						coords.push({ lat: p.lat(), lng: p.lng() });
					}
					payload.polygon = { path: coords };
				}
			} catch (_) {}

			// Emit a Livewire event for app code to consume
			try {
				window.Livewire.dispatch('lw-map:draw-complete', {payload: payload});
			} catch (_) {}

		});
	}

        function initOne(domId, cfg) {
                const el = document.getElementById(domId);
                if (!el) return false; // try later
                if (!isDisplayed(el)) {
                        // Element exists but is currently hidden/zero-sized. Retry later when it becomes visible.
                        return false;
                }
                if (LW.instances[domId]) return true; // already

		const lat = Number(cfg && cfg.lat);
		const lng = Number(cfg && cfg.lng);
		const zoom = Number(cfg && cfg.zoom);
		const mapOptions = (cfg && typeof cfg.mapOptions === 'object') ? cfg.mapOptions : {};

		const center = (!isNaN(lat) && !isNaN(lng)) ? { lat, lng } : { lat: 0, lng: 0 };
		const z = !isNaN(zoom) ? zoom : 8;

		const map = new google.maps.Map(el, { center, zoom: z, ...mapOptions });

		const inst = {
			id: domId,
			map,
			markers: [],
			clusterer: null,
			infoWindow: null,
			drawingManager: null,
			drawOverlay: null,
		};

		LW.instances[domId] = inst;

		// Initial markers/clustering
		if (Array.isArray(cfg && cfg.markers)) {
			setMarkers(inst, cfg.markers, !!cfg.useClusters, cfg.clusterOptions || {});
		}

		// Optional drawing tools
		if (cfg && cfg.drawType) {
			setupDrawing(inst, cfg.drawType);
		}

		return true;
	}

        function processQueue() {
                if (!LW.queue.length) return;
                ensureGoogle(() => {
                        LW.queue = LW.queue.filter(item => {
                                try { return !initOne(item.domId, item.config); } catch (_) { return true; }
                        });
                        if (LW.queue.length) {
                                scheduleQueueRun(250);
                        }
                });
        }

        // Public API: queue an init until DOM element and Google API are ready
        LW.queueInit = function (domId, config) {
                const key = String(domId);
                const entry = { domId: key, config: config || {} };
                const existingIndex = LW.queue.findIndex(item => item.domId === key);
                if (existingIndex === -1) {
                        LW.queue.push(entry);
                } else {
                        LW.queue[existingIndex] = entry;
                }

                let initialized = false;
                try { initialized = initOne(entry.domId, entry.config); } catch (_) { initialized = false; }
                if (initialized) {
                        LW.queue = LW.queue.filter(item => item.domId !== entry.domId);
                        return;
                }

                // Also schedule retries shortly after and until it becomes visible/ready
                scheduleQueueRun(0);
                scheduleQueueRun(200);
                if (document.readyState === 'complete') scheduleQueueRun(1000);
                else window.addEventListener('load', () => scheduleQueueRun(0), { once: true });
        };
	LW.queueInit.__isShim = false;
	try { LW.__queueInitShim = null; } catch (_) {}

	// Listen for server → browser updates from Livewire PHP
	try {
		window.addEventListener('lw-map-internal-update', (e) => {
			const d = e && e.detail ? e.detail : {};
			const id = d.id;
			if (!id) return;
			const inst = LW.instances[id];
			if (!inst) {
				// Instance missing: initialize from DOM attributes and replay once
				try {
					const el = document.getElementById(id);
					if (typeof LW.queueInit === 'function') {
						const cfg = el ? readCfg(el) : {};
						LW.queueInit(id, cfg);
					}
				} catch (_) {}
				setTimeout(() => {
					try { window.dispatchEvent(new CustomEvent('lw-map-internal-update', { detail: d })); } catch (_) {}
				}, 50);
				return;
			}

			// Update markers (and cluster state) if provided
			let markersUpdated = false;
			if (Array.isArray(d.markers)) {
				setMarkers(inst, d.markers, !!d.useClusters, d.clusterOptions || {});
				markersUpdated = true;
			}

			// Update zoom if provided
			if (typeof d.zoom === 'number' && isFinite(d.zoom)) {
				try { inst.map.setZoom(Number(d.zoom)); } catch (_) {}
			}

			// Update draw type if explicitly provided (allow null to clear)
			if (Object.prototype.hasOwnProperty.call(d, 'drawType')) {
				if (d.drawType) {
					setupDrawing(inst, d.drawType);
				} else {
					// Clear draw mode and any existing overlay
					try { if (inst.drawingManager && typeof inst.drawingManager.setMap === 'function') { inst.drawingManager.setMap(null); } } catch (_) {}
					inst.drawingManager = null;
					try { if (inst.drawOverlay && typeof inst.drawOverlay.setMap === 'function') { inst.drawOverlay.setMap(null); } } catch (_) {}
					inst.drawOverlay = null;
				}
			}

			// Update center if provided
			const hasCenter = (typeof d.centerLat === 'number' && typeof d.centerLng === 'number');
			if (hasCenter) {
				try { inst.map.setCenter({ lat: d.centerLat, lng: d.centerLng }); } catch (_) {}
			}

			// Handle autoFitBounds with precedence:
			// - If autoFitBounds is explicitly provided: when true, fit to markers; when false, do nothing.
			// - If not provided: preserve previous behavior (fit when markers updated and no explicit center).
			if (Object.prototype.hasOwnProperty.call(d, 'autoFitBounds')) {
				if (d.autoFitBounds) {
					fitToMarkers(inst, inst.markers);
				}
			} else if (!hasCenter && markersUpdated) {
				// No explicit center and markers changed: fit viewport to markers (legacy behavior)
				fitToMarkers(inst, inst.markers);
			}
		});
	} catch (_) {}

	// Listen for draw mode toggles from Livewire/browser
	try {
		window.addEventListener('lw-map:draw', function(e){
			const d = (e && e.detail) ? e.detail : {};
			const type = d && Object.prototype.hasOwnProperty.call(d, 'type') ? d.type : null;
			const targetIds = d && d.id ? [String(d.id)] : Object.keys(LW.instances);
			targetIds.forEach(function(mapId){
				const inst = LW.instances[mapId];
				if (!inst) return;
				if (!type) {
					// Exit draw mode: remove manager and overlay
					try { if (inst.drawingManager && typeof inst.drawingManager.setMap === 'function') { inst.drawingManager.setMap(null); } } catch(_) {}
					inst.drawingManager = null;
					try { if (inst.drawOverlay && typeof inst.drawOverlay.setMap === 'function') { inst.drawOverlay.setMap(null); } } catch(_) {}
					inst.drawOverlay = null;
				} else {
					setupDrawing(inst, type);
				}
			});
		});
	} catch(_) {}

	// Livewire v3 hook: resize visible maps after DOM updates (e.g., wizard steps)
	try {
		if (window.Livewire && typeof Livewire.hook === 'function') {
			Livewire.hook('message.processed', () => {
				document.querySelectorAll('[data-lw-map]').forEach(el => {
					const id = el.id;
					const inst = window.__LW_MAPS.instances[id];
					if (inst && el.offsetParent !== null && window.google && window.google.maps) {
						try { google.maps.event.trigger(inst.map, 'resize'); } catch (_) {}
						try { inst.map.setCenter(inst.map.getCenter()); } catch (_) {}
					}
				});
			});
		}
	} catch (_) {}
	// Auto-discovery: init any not-yet-initialized [data-lw-map] elements
	function toNum(v, d) { var n = Number(v); return Number.isFinite(n) ? n : d; }
	function toBool(v, d) {
		if (typeof v === 'boolean') return v;
		if (v == null) return d;
		var s = String(v).toLowerCase();
		if (s === '1' || s === 'true' || s === 'yes') return true;
		if (s === '0' || s === 'false' || s === 'no') return false;
		return d;
	}
	function readJsonAttr(el, name, fallback) {
		try {
			var raw = el && el.getAttribute ? el.getAttribute(name) : null;
			if (!raw) return fallback;
			return JSON.parse(raw);
		} catch (_) { return fallback; }
	}
	function readCfg(el) {
		return {
			lat: toNum(el && el.getAttribute ? el.getAttribute('data-lat') : null, 0),
			lng: toNum(el && el.getAttribute ? el.getAttribute('data-lng') : null, 0),
			zoom: toNum(el && el.getAttribute ? el.getAttribute('data-zoom') : null, 8),
			drawType: el && el.getAttribute ? (el.getAttribute('data-draw-type') || null) : null,
			useClusters: toBool(el && el.getAttribute ? el.getAttribute('data-use-clusters') : null, false),
			clusterOptions: readJsonAttr(el, 'data-cluster-options', {}),
			mapOptions: readJsonAttr(el, 'data-map-options', {}),
			markers: readJsonAttr(el, 'data-markers', []),
		};
	}
	function initMissingMaps() {
		if (typeof LW.queueInit !== 'function') return;
		document.querySelectorAll('[data-lw-map]').forEach(function(el){
			var id = el && el.id ? String(el.id) : null;
			if (!id) return;
			if (LW.instances && LW.instances[id]) return;
			LW.queueInit(id, readCfg(el));
		});
	}
	// Init at DOM ready
	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		setTimeout(initMissingMaps, 0);
	} else {
		document.addEventListener('DOMContentLoaded', function(){ initMissingMaps(); }, { once: true });
	}
	// Re-init after Livewire DOM morphs (tabs/wizards/modals)
	try {
		if (window.Livewire && typeof Livewire.hook === 'function') {
			Livewire.hook('message.processed', function(){ initMissingMaps(); });
		}
	} catch (_) {}

	// Attempt to process any queued maps that were pushed before this script executed
	try { processQueue(); } catch (_) {}
})();
