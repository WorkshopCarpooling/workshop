(() => {
  let googleMapsPromise = null;

  window.CAMPUS_LOCATIONS = {
    'Melaka Sentral': { lat: 2.2214, lng: 102.2504, label: 'Melaka Sentral Bus Terminal' },
    'Opposite SK Batu Berendam 2': { lat: 2.2512, lng: 102.2591, label: 'Opposite SK Batu Berendam 2' },
    'Mydin MITC': { lat: 2.2707, lng: 102.2856, label: 'Mydin Hypermarket MITC Ayer Keroh' },
    'Zoo Melaka': { lat: 2.2768, lng: 102.2996, label: 'Zoo Melaka, Lebuh Ayer Keroh' },
    'UTeM Main Campus (Durian Tunggal)': { lat: 2.3139, lng: 102.3183, label: 'UTeM Main Campus (Durian Tunggal)' },
    'UTeM Main Campus': { lat: 2.3139, lng: 102.3183, label: 'UTeM Main Campus (Durian Tunggal)' }
  };

  window.getStopCoordinates = function getStopCoordinates(stopName) {
    if (!stopName) return window.CAMPUS_LOCATIONS['UTeM Main Campus'];
    for (const [key, coords] of Object.entries(window.CAMPUS_LOCATIONS)) {
      if (stopName.toLowerCase().includes(key.toLowerCase()) || key.toLowerCase().includes(stopName.toLowerCase())) {
        return coords;
      }
    }
    return window.CAMPUS_LOCATIONS['UTeM Main Campus'];
  };

  window.loadGoogleMaps = function loadGoogleMaps() {
    if (window.google && window.google.maps) {
      return Promise.resolve(window.google.maps);
    }
    const apiKey = (window.googleMapsApiKey || '').trim();
    if (!apiKey || apiKey === 'YOUR_GOOGLE_MAPS_API_KEY') {
      return Promise.reject(new Error('Google Maps API key not configured'));
    }
    if (!googleMapsPromise) {
      googleMapsPromise = new Promise((resolve, reject) => {
        const callbackName = '__initRideUtemGoogleMaps';
        window[callbackName] = () => {
          resolve(window.google.maps);
        };
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&libraries=places,geometry&callback=${callbackName}`;
        script.async = true;
        script.defer = true;
        script.onerror = () => {
          googleMapsPromise = null;
          reject(new Error('Failed to load Google Maps JavaScript API'));
        };
        document.head.appendChild(script);
      });
    }
    return googleMapsPromise;
  };

  window.computeGoogleRoute = async function computeGoogleRoute({
    originLat,
    originLng,
    originName = '',
    destLat = 2.3139,
    destLng = 102.3183,
    destName = 'UTeM Main Campus (Durian Tunggal)'
  }) {
    const endpoint = window.routesComputeUrl || 'api/routes/compute';
    const csrfToken = window.bookingCsrf || document.querySelector('input[name="_token"]')?.value || '';

    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({
        origin_lat: originLat,
        origin_lng: originLng,
        origin_name: originName,
        dest_lat: destLat,
        dest_lng: destLng,
        dest_name: destName,
        _token: csrfToken
      })
    });

    if (!response.ok) {
      throw new Error('Could not compute route');
    }
    return response.json();
  };

  window.buildGoogleMapsDirectionsUrl = function buildGoogleMapsDirectionsUrl(originLat, originLng, destLat = 2.3139, destLng = 102.3183) {
    return `https://www.google.com/maps/dir/?api=1&origin=${encodeURIComponent(`${originLat},${originLng}`)}&destination=${encodeURIComponent(`${destLat},${destLng}`)}&travelmode=driving`;
  };
})();
