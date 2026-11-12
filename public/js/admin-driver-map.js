(() => {
  // The live feed is independent of the renderer. Leaflet remains the fallback.
  window.createAdminDriverMap = async (container, { reducedMotion = false, moduleUrl = '/vendor/maplibre/maplibre-gl.mjs' } = {}) => {
    let gl, native, timer;
    try {
      gl = window.maplibregl || await Promise.race([
        import(moduleUrl),
        new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('Map loading timed out')), 8000); })
      ]);
      clearTimeout(timer);
      gl.setWorkerCount(1);
      native = new gl.Map({
        container, center: [102.3183, 2.3139], zoom: 14,
        pitch: 0, bearing: 0,
        maxPitch: 55, maxZoom: 19, renderWorldCopies: false,
        style: {
          version: 8,
          sources: { campus: { type: 'raster', tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize: 256, maxzoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' } },
          layers: [{ id: 'campus', type: 'raster', source: 'campus', paint: { 'raster-saturation': -.6, 'raster-contrast': -.1, 'raster-fade-duration': 150 } }]
        }
      });
      native.on('error', (event) => { container.dataset.mapError = event.error?.message || 'Map resource unavailable'; });
      native.addControl(new gl.NavigationControl({ showCompass: true, visualizePitch: true }), 'top-left');
    } catch (error) {
      clearTimeout(timer);
      native?.remove();
      container.replaceChildren();
      container.dataset.renderer = 'flat';
      container.dataset.mapError = error.message;
      return null;
    }
    container.dataset.renderer = 'angled';
    let serial = 0, angleFrame = null, styleReady = false;
    native.on('style.load', () => { styleReady = true; container.dataset.mapStyle = 'ready'; });
    native.on('load', () => { container.dataset.mapLoaded = 'true'; });
    const xy = (point) => Array.isArray(point) ? [point[1], point[0]] : [point.lng, point.lat];
    const plain = (point) => ({ lat: point.lat, lng: point.lng });
    const map = {
      setView(point, zoom) { native.jumpTo({ center: xy(point), zoom }); return this; },
      panTo(point) { native.jumpTo({ center: xy(point) }); return this; },
      fitBounds(points, options = {}) {
        if (!points.length) return this;
        const bounds = new gl.LngLatBounds();
        points.forEach((point) => bounds.extend(xy(point)));
        native.fitBounds(bounds, { padding: options.padding?.[0] ?? 40, maxZoom: options.maxZoom ?? 16, duration: 0 });
        return this;
      },
      on(event, callback) { native.on(event, callback); return this; },
      setAngle(angled) {
        cancelAnimationFrame(angleFrame);
        const pitch = native.getPitch(), bearing = native.getBearing(), start = performance.now();
        const step = (time) => {
          const t = reducedMotion ? 1 : Math.min(1, (time - start) / 700);
          const ease = t * t * (3 - 2 * t);
          // Separate from native camera easing: live centring must not cancel tilt.
          native.jumpTo({ pitch: pitch + ((angled ? 38 : 0) - pitch) * ease, bearing: bearing + ((angled ? -35 : 0) - bearing) * ease });
          if (t < 1) angleFrame = requestAnimationFrame(step);
        };
        angleFrame = requestAnimationFrame(step);
      },
      isAngled() { return native.getPitch() > 1; },
      screenHeading(point, heading) {
        const radians = heading * Math.PI / 180;
        const start = native.project(xy(point));
        const end = native.project([point.lng + Math.sin(radians) * .0001 / Math.cos(point.lat * Math.PI / 180), point.lat + Math.cos(radians) * .0001]);
        return Math.atan2(end.x - start.x, start.y - end.y) * 180 / Math.PI;
      }
    };
    const makeMarker = (point, element, { title = '', pitch = 'viewport' } = {}) => {
      if (title) { element.setAttribute('aria-label', title); element.setAttribute('role', 'button'); element.tabIndex = 0; }
      const marker = new gl.Marker({ element, anchor: 'center', pitchAlignment: pitch, rotationAlignment: pitch === 'map' ? 'map' : 'viewport' });
      marker.setSubpixelPositioning(true).setLngLat(xy(point));
      return {
        addTo() { marker.addTo(native); return this; },
        setLatLng(value) { marker.setLngLat(xy(value)); return this; },
        getLatLng() { return plain(marker.getLngLat()); },
        getElement() { return element; },
        bindTooltip() { return this; }, closeTooltip() { return this; },
        on(event, callback) {
          element.addEventListener(event, callback);
          if (event === 'click') element.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); callback(); } });
          return this;
        },
        remove() { marker.remove(); }
      };
    };
    const pointMarker = (point, radius, options = {}) => {
      const element = document.createElement('div');
      element.className = 'admin-map-point';
      element.style.width = element.style.height = `${radius * 2}px`;
      element.style.border = `${options.weight ?? 1}px solid ${options.color || '#244b39'}`;
      element.style.background = options.fillColor || '#fff';
      return makeMarker(point, element, { pitch: 'map' });
    };
    return {
      map: () => map,
      // Raster tiles are already included in the native map style.
      tileLayer: () => ({ addTo() {} }),
      divIcon: (options) => options,
      latLngBounds: (points) => points,
      marker: (point, options) => {
        const element = document.createElement('div');
        element.className = options.icon.className;
        element.style.width = `${options.icon.iconSize[0]}px`;
        element.style.height = `${options.icon.iconSize[1]}px`;
        element.style.zIndex = '10';
        element.append(options.icon.html);
        return makeMarker(point, element, { title: options.title });
      },
      circleMarker: (point, options) => pointMarker(point, options.radius, options),
      circle: (point, options) => {
        const circle = pointMarker(point, 1, { color: options.color });
        const element = circle.getElement();
        element.classList.add('admin-map-accuracy');
        let metres = options.radius || 0;
        const resize = () => {
          const latitude = circle.getLatLng().lat;
          const pixels = Math.min(400, metres / (40075016.686 * Math.cos(latitude * Math.PI / 180) / (512 * 2 ** native.getZoom())));
          element.style.width = element.style.height = `${Math.max(0, pixels * 2)}px`;
          element.hidden = metres <= 0;
        };
        native.on('zoom', resize);
        return {
          addTo() { circle.addTo(); resize(); return this; },
          setLatLng(value) { circle.setLatLng(value); },
          setRadius(value) { metres = value; resize(); },
          setStyle(value) { element.style.borderColor = value.color; },
          remove() { native.off('zoom', resize); circle.remove(); }
        };
      },
      polyline: (points, options) => {
        const id = `driver-route-${++serial}`;
        let removed = false;
        const data = () => ({ type: 'FeatureCollection', features: points.length > 1 ? [{ type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: points.map(xy) } }] : [] });
        const add = () => {
          if (removed) return;
          native.addSource(id, { type: 'geojson', data: data() });
          const paint = { 'line-color': options.color, 'line-width': options.weight, 'line-opacity': options.opacity ?? 1 };
          if (options.dashArray) paint['line-dasharray'] = options.dashArray.split(' ').map(Number).map((n) => n / options.weight);
          native.addLayer({ id, type: 'line', source: id, layout: { 'line-cap': 'round', 'line-join': 'round' }, paint });
        };
        return {
          addTo() { if (styleReady || native.isStyleLoaded()) add(); else native.once('style.load', add); return this; },
          setLatLngs(value) { points = value; native.getSource(id)?.setData(data()); },
          remove() { removed = true; if (native.getLayer(id)) native.removeLayer(id); if (native.getSource(id)) native.removeSource(id); }
        };
      }
    };
  };
})();
