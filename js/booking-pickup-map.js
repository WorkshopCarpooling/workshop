(() => {
  document.addEventListener('click', async (event) => {
    const button = event.target.closest('.pickup-map-open');
    if (!button) return;

    const mapElement = button.nextElementSibling;
    if (!mapElement) return;

    const isOpen = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', String(!isOpen));
    mapElement.hidden = isOpen;
    button.textContent = isOpen ? 'View pickup & route on Google Maps' : 'Hide Google Map';
    if (isOpen) return;

    if (mapElement.dataset.loaded === 'true') {
      return;
    }

    const stopName = mapElement.dataset.stopName || '';
    const destName = mapElement.dataset.dest || 'UTeM Main Campus (Durian Tunggal)';
    const fallbackCoords = window.getStopCoordinates ? window.getStopCoordinates(stopName) : { lat: 2.2707, lng: 102.2856 };
    const destCoords = window.getStopCoordinates ? window.getStopCoordinates(destName) : { lat: 2.3139, lng: 102.3183 };

    const lat = mapElement.dataset.lat && mapElement.dataset.lat !== ''
      ? Number(mapElement.dataset.lat)
      : fallbackCoords.lat;
    const lng = mapElement.dataset.lng && mapElement.dataset.lng !== ''
      ? Number(mapElement.dataset.lng)
      : fallbackCoords.lng;

    mapElement.innerHTML = '<div class="google-route-status">Loading Google Maps & Routes API…</div>';

    let routeData = null;
    try {
      if (window.computeGoogleRoute) {
        routeData = await window.computeGoogleRoute({
          originLat: lat,
          originLng: lng,
          originName: stopName,
          destLat: destCoords.lat,
          destLng: destCoords.lng,
          destName
        });
      }
    } catch {
      routeData = null;
    }

    const directionsUrl = window.buildGoogleMapsDirectionsUrl
      ? window.buildGoogleMapsDirectionsUrl(lat, lng, destCoords.lat, destCoords.lng)
      : `https://www.google.com/maps/dir/?api=1&origin=${lat},${lng}&destination=${destCoords.lat},${destCoords.lng}&travelmode=driving`;

    const summaryBar = document.createElement('div');
    summaryBar.className = 'google-route-bar';
    const distanceInfo = routeData ? `${routeData.distanceText} · ~${routeData.durationText} drive` : `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    summaryBar.innerHTML = `
      <div class="google-route-meta">
        <b>Google Routes API:</b>
        <span>${distanceInfo}</span>
      </div>
      <a class="google-route-link" href="${directionsUrl}" target="_blank" rel="noopener noreferrer">Open in Google Maps ↗</a>
    `;

    const canvasWrap = document.createElement('div');
    canvasWrap.className = 'google-map-canvas';
    canvasWrap.style.height = '240px';
    canvasWrap.style.width = '100%';
    canvasWrap.style.borderRadius = '8px';
    canvasWrap.style.overflow = 'hidden';

    mapElement.replaceChildren(summaryBar, canvasWrap);

    try {
      const gmaps = await window.loadGoogleMaps();
      const map = new gmaps.Map(canvasWrap, {
        center: { lat, lng },
        zoom: 15,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true
      });

      const pickupMarker = new gmaps.Marker({
        position: { lat, lng },
        map,
        title: stopName || 'Passenger pickup point'
      });

      const infoWindow = new gmaps.InfoWindow({
        content: `<strong>${stopName || 'Passenger pickup point'}</strong><br><small>${distanceInfo}</small>`
      });
      infoWindow.open(map, pickupMarker);

      if (routeData && Array.isArray(routeData.path) && routeData.path.length > 1) {
        const destMarker = new gmaps.Marker({
          position: { lat: destCoords.lat, lng: destCoords.lng },
          map,
          title: destName
        });
        const routeLine = new gmaps.Polyline({
          path: routeData.path,
          geodesic: true,
          strokeColor: '#2f684e',
          strokeOpacity: 0.9,
          strokeWeight: 5
        });
        routeLine.setMap(map);

        const bounds = new gmaps.LatLngBounds();
        routeData.path.forEach((pt) => bounds.extend(pt));
        map.fitBounds(bounds, 40);
      }

      mapElement.dataset.loaded = 'true';
    } catch {
      // Render interactive Google Maps embed with pickup coordinates
      const query = stopName ? `${stopName}, Melaka, Malaysia (${lat},${lng})` : `${lat},${lng}`;
      const iframe = document.createElement('iframe');
      iframe.src = `https://www.google.com/maps?q=${encodeURIComponent(`${lat},${lng}`)}&z=15&output=embed`;
      iframe.width = '100%';
      iframe.height = '240';
      iframe.style.border = '0';
      iframe.loading = 'lazy';
      iframe.referrerPolicy = 'no-referrer-when-downgrade';
      iframe.title = `Google Map for ${query}`;
      canvasWrap.replaceChildren(iframe);
      mapElement.dataset.loaded = 'true';
    }
  });
})();
