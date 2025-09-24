(function () {
	// Global bootstrap if not already present
	window.__LW_MAPS = window.__LW_MAPS || {
		instances: {},
		queue: [],
		ready: false,
	};
	const LW = window.__LW_MAPS;

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
				} catch (_) {}
			}
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

			// Emit a browser event for app code to consume (and optionally forward to Livewire server)
			try {
				window.dispatchEvent(new CustomEvent('lw-map:draw-complete', { detail: payload }));
			} catch (_) {}

		});
	}

	function initOne(domId, cfg) {
		const el = document.getElementById(domId);
		if (!el) return false; // try later
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
		});
	}

	// Public API: queue an init until DOM element and Google API are ready
	LW.queueInit = function (domId, config) {
		LW.queue.push({ domId, config });
		// Try immediately in case everything is ready already
		try { initOne(domId, config); } catch (_) {}
		// Also schedule retries shortly after
		setTimeout(processQueue, 0);
		setTimeout(processQueue, 200);
		if (document.readyState === 'complete') setTimeout(processQueue, 1000);
		else window.addEventListener('load', () => setTimeout(processQueue, 0), { once: true });
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
			if (!inst) return;

			if (Array.isArray(d.markers)) {
				setMarkers(inst, d.markers, !!d.useClusters, d.clusterOptions || {});
			}
			if (typeof d.centerLat === 'number' && typeof d.centerLng === 'number') {
				try { inst.map.setCenter({ lat: d.centerLat, lng: d.centerLng }); } catch (_) {}
			} else if (Array.isArray(d.markers)) {
				// No explicit center provided: fit viewport to markers
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
	// Attempt to process any queued maps that were pushed before this script executed
	try { processQueue(); } catch (_) {}
})();
