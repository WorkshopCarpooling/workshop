(() => {
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
      const maplibregl = await window.loadMapLibre();
      if (!mapElement._mapLibreMap) {
        const point = [Number(mapElement.dataset.lng), Number(mapElement.dataset.lat)];
        mapElement._mapLibreMap = new maplibregl.Map({
          container: mapElement,
          style: window.createOpenStreetMapStyle(),
          center: point,
          zoom: 16,
          maxZoom: 19,
          scrollZoom: false,
          attributionControl: true
        });
        new maplibregl.Marker({ color: '#4f805d' })
          .setLngLat(point)
          .setPopup(new maplibregl.Popup({ offset: 26 }).setText('Passenger pickup point'))
          .addTo(mapElement._mapLibreMap)
          .togglePopup();
      }
      requestAnimationFrame(() => mapElement._mapLibreMap.resize());
    } catch {
      mapElement.hidden = true;
      button.setAttribute('aria-expanded', 'false');
      button.textContent = 'Map unavailable. Try again';
    }
  });
})();
