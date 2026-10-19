(() => {
  let leafletPromise;

  function loadLeaflet() {
    if (window.L) return Promise.resolve();
    if (leafletPromise) return leafletPromise;
    leafletPromise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
      script.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
      script.crossOrigin = '';
      script.onload = resolve;
      script.onerror = reject;
      document.head.append(script);
    });
    return leafletPromise;
  }

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('.pickup-map-open');
    if (!button) return;
    const mapElement = button.nextElementSibling;
    const isOpen = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', String(!isOpen));
    mapElement.hidden = isOpen;
    button.textContent = isOpen ? 'View pickup point on map' : 'Hide pickup map';
    if (isOpen) return;

    try {
      await loadLeaflet();
      if (!mapElement._leafletMap) {
        const point = [Number(mapElement.dataset.lat), Number(mapElement.dataset.lng)];
        mapElement._leafletMap = L.map(mapElement, { scrollWheelZoom: false }).setView(point, 16);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>'
        }).addTo(mapElement._leafletMap);
        L.marker(point).addTo(mapElement._leafletMap).bindPopup('Passenger pickup point').openPopup();
      }
      requestAnimationFrame(() => mapElement._leafletMap.invalidateSize());
    } catch {
      mapElement.hidden = true;
      button.setAttribute('aria-expanded', 'false');
      button.textContent = 'Map unavailable. Try again';
    }
  });
})();
