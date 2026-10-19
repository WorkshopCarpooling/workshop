(() => {
  const form = document.getElementById('rideSearchForm');
  const results = document.getElementById('rideResults');
  const empty = document.getElementById('findEmpty');
  const count = document.getElementById('resultsCount');
  const toast = document.getElementById('toast');
  const pickupMapPanel = document.getElementById('pickupMapPanel');
  const mapLoadError = document.getElementById('mapLoadError');
  const pickupSearchForm = document.getElementById('pickupPlaceSearch');
  const pickupSearchInput = document.getElementById('pickupPlaceQuery');
  const pickupSearchStatus = document.getElementById('pickupSearchStatus');
  const pickupSearchResults = document.getElementById('pickupSearchResults');
  const travelDepartureInput = document.getElementById('travelDeparture');
  const rides = Array.isArray(window.availableRides) ? window.availableRides : [];
  let toastTimer;
  let pickupMap;
  let pickupMarker;
  let leafletLoadPromise;
  let lastPlaceSearchAt = 0;
  const campusCenter = { lat: 2.3139, lng: 102.3183 };

  function localDateTimeValue(date) {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 16);
  }

  travelDepartureInput.min = localDateTimeValue(new Date());

  function notify(message) {
    toast.textContent = message;
    toast.classList.add('visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('visible'), 3200);
  }

  function setPickupPoint(position, label = 'Pinned pickup point') {
    const latitude = typeof position.lat === 'function' ? position.lat() : position.lat;
    const longitude = typeof position.lng === 'function' ? position.lng() : position.lng;
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;

    const point = [latitude, longitude];
    if (pickupMarker) pickupMarker.setLatLng(point);
    document.getElementById('pickupLatitude').value = latitude.toFixed(6);
    document.getElementById('pickupLongitude').value = longitude.toFixed(6);
    document.getElementById('pickupPointLabel').textContent = label;
    document.getElementById('pickupCoordinates').textContent = `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;
  }

  async function openPickupMap() {
    const toggle = document.getElementById('togglePickupMap');
    if (toggle.getAttribute('aria-expanded') !== 'true') {
      toggle.setAttribute('aria-expanded', 'true');
      toggle.innerHTML = 'Close map <span>⌃</span>';
      pickupMapPanel.hidden = false;
    }
    try {
      await loadLeaflet();
      if (!pickupMap) initializePickupMap();
      setTimeout(() => pickupMap.invalidateSize(), 0);
      return true;
    } catch {
      mapLoadError.hidden = false;
      pickupSearchStatus.textContent = 'The map could not load. Try again or choose a pickup area from the list.';
      return false;
    }
  }

  function initializePickupMap() {
    const savedLat = Number(document.getElementById('pickupLatitude').value);
    const savedLng = Number(document.getElementById('pickupLongitude').value);
    const hasSavedPoint = Number.isFinite(savedLat) && Number.isFinite(savedLng);
    const startPoint = hasSavedPoint ? [savedLat, savedLng] : [campusCenter.lat, campusCenter.lng];
    pickupMap = L.map('pickupMap', { scrollWheelZoom: false }).setView(startPoint, hasSavedPoint ? 16 : 14);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>'
    }).addTo(pickupMap);
    pickupMarker = L.marker(startPoint, { draggable: true }).addTo(pickupMap);
    pickupMarker.on('dragend', () => setPickupPoint(pickupMarker.getLatLng()));
    pickupMap.on('click', (event) => setPickupPoint(event.latlng));
    if (hasSavedPoint) setPickupPoint({ lat: savedLat, lng: savedLng }, document.getElementById('pickupPointLabel').textContent);
  }

  function loadLeaflet() {
    if (window.L) return Promise.resolve();
    if (leafletLoadPromise) return leafletLoadPromise;
    leafletLoadPromise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
      script.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
      script.crossOrigin = '';
      script.onload = resolve;
      script.onerror = reject;
      document.head.append(script);
    });
    return leafletLoadPromise;
  }

  pickupSearchForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const query = pickupSearchInput.value.trim();
    if (query.length < 3) {
      pickupSearchStatus.textContent = 'Enter at least 3 characters to search.';
      return;
    }
    if (Date.now() - lastPlaceSearchAt < 1100) {
      pickupSearchStatus.textContent = 'Please wait a moment before searching again.';
      return;
    }
    lastPlaceSearchAt = Date.now();
    const submitButton = pickupSearchForm.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    pickupSearchStatus.textContent = 'Searching places…';
    pickupSearchResults.replaceChildren();
    try {
      const mapReady = await openPickupMap();
      if (!mapReady) return;
      const params = new URLSearchParams({ format: 'jsonv2', limit: '5', countrycodes: 'my', q: query });
      const response = await fetch('https://nominatim.openstreetmap.org/search?' + params.toString(), { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('Place search is unavailable.');
      const places = await response.json();
      if (!places.length) {
        pickupSearchStatus.textContent = 'No places found. Try a nearby town or landmark.';
        return;
      }
      pickupSearchStatus.textContent = 'Choose the correct result:';
      places.forEach((place) => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'pickup-result-option';
        option.textContent = place.display_name;
        option.addEventListener('click', () => {
          const point = { lat: Number(place.lat), lng: Number(place.lon) };
          pickupMap.setView([point.lat, point.lng], 17);
          setPickupPoint(point, place.display_name);
          pickupSearchStatus.textContent = 'Pickup point selected.';
          pickupSearchResults.replaceChildren();
        });
        pickupSearchResults.append(option);
      });
    } catch {
      pickupSearchStatus.textContent = 'Place search failed. You can still tap the map to set your pickup pin.';
    } finally {
      submitButton.disabled = false;
    }
  });
  document.getElementById('togglePickupMap').addEventListener('click', (event) => {
    const button = event.currentTarget;
    const isOpen = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', String(!isOpen));
    button.innerHTML = isOpen ? 'Open map <span>⌄</span>' : 'Close map <span>⌃</span>';
    pickupMapPanel.hidden = isOpen;
    if (!isOpen) openPickupMap();
  });

  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function makeRideCard(ride, index) {
    const card = element('article', 'find-ride-card');
    const top = element('div', 'find-ride-top');
    const driver = element('div', 'find-driver');
    driver.append(element('span', 'find-driver-avatar', ride.initials || 'D'));
    const driverName = element('span');
    driverName.append(element('b', '', ride.driver), element('small', '', 'UTeM driver'));
    driver.append(driverName);
    const cost = element('div', 'find-ride-cost');
    cost.append(element('b', '', `RM ${ride.cost}`), element('small', '', 'per passenger seat'));
    top.append(driver, cost);

    const route = element('div', 'find-route-summary');
    const departure = element('div', 'find-departure');
    const dateText = new Date(`${ride.departureDate}T00:00:00`).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
    departure.append(element('b', '', ride.time), element('small', '', dateText));
    const points = element('div', 'find-route-points');
    const line = element('span', 'find-route-line');
    line.setAttribute('aria-hidden', 'true');
    line.append(document.createElement('i'), document.createElement('i'));
    const labels = element('span', 'find-route-labels');
    labels.append(element('b', '', ride.pickup), element('small', '', ride.destination));
    points.append(line, labels);
    const availability = ride.status === 'in_progress' ? 'Trip underway' : `${ride.seats} seat${ride.seats === 1 ? '' : 's'} open`;
    const availabilityClass = ride.status === 'in_progress' ? 'find-seat-pill ride-underway' : 'find-seat-pill';
    route.append(departure, points, element('span', availabilityClass, availability));

    const detailsButton = element('button', 'find-details-button', 'View trip details');
    detailsButton.type = 'button';
    detailsButton.setAttribute('aria-expanded', 'false');
    detailsButton.setAttribute('aria-controls', `ride-detail-${index}`);
    let requestAction;
    if (ride.status === 'in_progress') {
      requestAction = element('button', 'find-request-button', 'Trip underway');
      requestAction.type = 'button';
      requestAction.disabled = true;
    } else if (ride.bookingStatus === 'pending' || ride.bookingStatus === 'accepted') {
      requestAction = element('button', 'find-request-button', ride.bookingStatus === 'pending' ? 'Request pending' : 'Seat confirmed');
      requestAction.type = 'button';
      requestAction.disabled = true;
    } else {
      requestAction = document.createElement('form');
      requestAction.method = 'post';
      requestAction.className = 'find-request-form';
      const hidden = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = name; input.value = value;
        return input;
      };
      requestAction.append(hidden('csrf_token', window.bookingCsrf || ''), hidden('action', 'request_seat'), hidden('ride_id', ride.id));
      requestAction.append(hidden('pickup_label', ''), hidden('pickup_latitude', ''), hidden('pickup_longitude', ''));
      requestAction.addEventListener('submit', () => {
        requestAction.elements.pickup_label.value = document.getElementById('pickupPointLabel').textContent === 'No exact map point selected' ? '' : document.getElementById('pickupPointLabel').textContent;
        requestAction.elements.pickup_latitude.value = document.getElementById('pickupLatitude').value;
        requestAction.elements.pickup_longitude.value = document.getElementById('pickupLongitude').value;
      });
      const seatCount = document.createElement('select');
      seatCount.name = 'seats_requested';
      seatCount.setAttribute('aria-label', 'Seats to request');
      for (let seat = 1; seat <= Math.min(3, ride.seats); seat++) {
        const option = document.createElement('option'); option.value = String(seat); option.textContent = `${seat} seat${seat === 1 ? '' : 's'}`;
        seatCount.append(option);
      }
      const requestButton = element('button', 'find-request-button', 'Request seat');
      requestButton.type = 'submit';
      requestAction.append(seatCount, requestButton);
    }
    const actions = element('div', 'find-ride-actions');
    actions.append(detailsButton, requestAction);
    const extra = element('div', 'find-extra');
    extra.id = `ride-detail-${index}`;
    extra.hidden = true;
    const vehicle = [ride.vehicleColor, ride.vehicleType].filter(Boolean).join(' ');
    const detailParts = [`Pickup: ${ride.pickup}`, `Destination: ${ride.destination}`, vehicle ? `Vehicle: ${vehicle}` : '', ride.notes ? `Driver note: ${ride.notes}` : ''].filter(Boolean);
    extra.textContent = detailParts.join(' · ');
    detailsButton.addEventListener('click', () => {
      const expanded = detailsButton.getAttribute('aria-expanded') === 'true';
      detailsButton.setAttribute('aria-expanded', String(!expanded));
      extra.hidden = expanded;
      detailsButton.textContent = expanded ? 'View trip details' : 'Hide trip details';
    });

    card.append(top, route, actions, extra);
    return card;
  }

  function renderRides(filteredRides) {
    results.replaceChildren(...filteredRides.map(makeRideCard));
    empty.hidden = filteredRides.length > 0;
    count.textContent = `${filteredRides.length} ${filteredRides.length === 1 ? 'trip' : 'trips'}`;
    if (rides.length === 0) {
      empty.querySelector('b').textContent = 'No eligible rides available';
      empty.querySelector('p').textContent = 'Only future scheduled rides from active, admin-approved student or staff drivers appear here. Ask an approved driver to publish a ride, then refresh this page.';
    } else if (filteredRides.length === 0) {
      empty.querySelector('b').textContent = 'No rides match those filters';
      empty.querySelector('p').textContent = 'Try another pickup point, destination, departure date or time, or passenger count.';
    }
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const pickup = document.getElementById('pickupArea').value.trim().toLocaleLowerCase();
    const destination = document.getElementById('rideDestination').value.trim().toLocaleLowerCase();
    const selectedDeparture = travelDepartureInput.value;
    const passengers = Number(document.getElementById('passengerCount').value || 1);
    if (travelDepartureInput.validity.rangeUnderflow) {
      notify('Choose a departure date and time that have not passed.');
      return;
    }
    const matches = rides.filter((ride) =>
      ride.seats >= passengers &&
      (!selectedDeparture || ride.departureAt >= selectedDeparture) &&
      (!pickup || ride.pickup.toLocaleLowerCase().includes(pickup)) &&
      (!destination || ride.destination.toLocaleLowerCase().includes(destination))
    );
    renderRides(matches);
  });

  renderRides(rides);
})();
