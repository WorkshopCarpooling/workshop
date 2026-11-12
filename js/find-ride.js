(() => {
  const form = document.getElementById('rideSearchForm');
  const results = document.getElementById('rideResults');
  const empty = document.getElementById('findEmpty');
  const count = document.getElementById('resultsCount');
  const toast = document.getElementById('toast');
  const travelDepartureInput = document.getElementById('travelDeparture');
  const routeSelect = document.getElementById('routeDirection');
  const pickupSelect = document.getElementById('pickupArea');
  const passengerSelect = document.getElementById('passengerCount');
  const rides = Array.isArray(window.availableRides) ? window.availableRides : [];

  // Google Maps & Routes API elements
  const toggleMapBtn = document.getElementById('togglePickupMap');
  const mapPanel = document.getElementById('pickupMapPanel');
  const mapContainer = document.getElementById('pickupMap');
  const placeQueryInput = document.getElementById('pickupPlaceQuery');
  const placeSearchBtn = document.getElementById('pickupPlaceSearchBtn');
  const searchStatus = document.getElementById('pickupSearchStatus');
  const searchResults = document.getElementById('pickupSearchResults');
  const routeMetricsEl = document.getElementById('googleRouteMetrics');
  const directionsLinkEl = document.getElementById('googleDirectionsLink');
  const pickupPointLabelEl = document.getElementById('pickupPointLabel');
  const pickupCoordsEl = document.getElementById('pickupCoordinates');
  const pickupLatInput = document.getElementById('pickupLatitude');
  const pickupLngInput = document.getElementById('pickupLongitude');

  // Hero Mobility Showcase & Bento UI elements
  const showcaseStage = document.getElementById('mobilityShowcase');
  const carFigureMotion = document.getElementById('carFigureMotion');
  const showcaseCarTag = document.getElementById('showcaseCarTag');
  const showcaseCarTitle = document.getElementById('showcaseCarTitle');
  const showcaseCarSubtitle = document.getElementById('showcaseCarSubtitle');
  const telemetryFuel = document.getElementById('telemetryFuel');
  const telemetryCorridor = document.getElementById('telemetryCorridor');
  const toggleCruiseBtn = document.getElementById('toggleCruiseAnim');
  const toggleHeadlampBtn = document.getElementById('toggleHeadlamp');
  const jumpToMapBtn = document.getElementById('jumpToMapBtn');
  const trimPills = document.querySelectorAll('.trim-pill');
  const modePills = document.querySelectorAll('.mode-pill');

  // Concierge & Bento Date/Cost elements
  const conciergeRidesStat = document.getElementById('conciergeRidesStat');
  const conciergeRouteStat = document.getElementById('conciergeRouteStat');
  const liveFilterSummaryText = document.getElementById('liveFilterSummaryText');
  const tileScrollRides = document.getElementById('tileScrollRides');
  const tileScrollMap = document.getElementById('tileScrollMap');
  const tileFilterCorridors = document.getElementById('tileFilterCorridors');
  const quickResetFilters = document.getElementById('quickResetFilters');
  const quickMorningSlot = document.getElementById('quickMorningSlot');
  const quickFocusMap = document.getElementById('quickFocusMap');
  const quickTriggerSearch = document.getElementById('quickTriggerSearch');
  const emptyResetFiltersBtn = document.getElementById('emptyResetFiltersBtn');

  const bentoDateFullLabel = document.getElementById('bentoDateFullLabel');
  const bentoDayNumber = document.getElementById('bentoDayNumber');
  const bentoMonthLabel = document.getElementById('bentoMonthLabel');
  const bentoTimeValue = document.getElementById('bentoTimeValue');
  const bentoTimePeriod = document.getElementById('bentoTimePeriod');
  const bentoPrevDay = document.getElementById('bentoPrevDay');
  const bentoNextDay = document.getElementById('bentoNextDay');
  const focusDateTimeBtn = document.getElementById('focusDateTimeBtn');
  const timePresetChips = document.querySelectorAll('.time-preset-chip');
  const bentoCostRangeText = document.getElementById('bentoCostRangeText');
  const bentoPassMeta = document.getElementById('bentoPassMeta');
  const sideCorridorButtons = document.querySelectorAll('[data-select-route]');

  // Malaysian Campus Fleet Definitions
  const MALAYSIAN_FLEET = {
    myvi: {
      tag: 'EEV · Smart Eco Hatchback',
      title: 'Perodua Myvi 1.5 AV',
      subtitle: "Malaysia's #1 student & staff campus carpool vehicle · 4 passenger seats",
      fuel: '🌱 Eco-Idle · ~21.1 km/L'
    },
    emas: {
      tag: 'EV · Zero-Emission Crossover',
      title: 'Proton e.MAS 7 Prime',
      subtitle: "Malaysia's first national electric campus SUV · Quiet zero-emission ride",
      fuel: '⚡ 100% Electric · 0g CO₂/km'
    },
    bezza: {
      tag: 'EEV · Campus Eco Sedan',
      title: 'Perodua Bezza 1.3 X',
      subtitle: 'High-efficiency Melaka Sentral & Ayer Keroh corridor sedan · 4 seats',
      fuel: '🌱 EEV Sedan · ~22.0 km/L'
    },
    s70: {
      tag: '1.5T · Executive Corridor Sedan',
      title: 'Proton S70 Flagship',
      subtitle: 'Comfortable staff & student express carpool sedan · 4 passenger seats',
      fuel: '✨ Eco-Drive · Smooth Highway Cruise'
    }
  };

  let toastTimer;
  let googleMapInstance = null;
  let googlePickupMarker = null;
  let googleDestMarker = null;
  let googleRoutePolyline = null;
  let currentOrigin = { lat: 2.2707, lng: 102.2856, label: 'Mydin Hypermarket MITC Ayer Keroh' };
  let currentDestination = { lat: 2.3139, lng: 102.3183, label: 'UTeM Main Campus (Durian Tunggal)' };
  let activeDirectionMode = 'all';

  function localDateTimeValue(date) {
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
  }

  if (travelDepartureInput) {
    travelDepartureInput.min = localDateTimeValue(new Date());
  }

  function notify(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('visible'), 3200);
  }

  // Trigger Malaysian Car Showcase Animation & Spec Update
  function selectMalaysianCar(carKey, customOverride = null) {
    const spec = customOverride || MALAYSIAN_FLEET[carKey] || MALAYSIAN_FLEET.myvi;
    if (showcaseStage) {
      showcaseStage.setAttribute('data-car', carKey);
    }
    trimPills.forEach((pill) => {
      pill.classList.toggle('active', pill.dataset.carKey === carKey);
    });

    if (carFigureMotion) {
      carFigureMotion.classList.remove('car-switching');
      void carFigureMotion.offsetWidth; // Force reflow for crisp animation replay
      carFigureMotion.classList.add('car-switching');
    }

    if (showcaseCarTag) showcaseCarTag.textContent = spec.tag;
    if (showcaseCarTitle) showcaseCarTitle.textContent = spec.title;
    if (showcaseCarSubtitle) showcaseCarSubtitle.textContent = spec.subtitle;
    if (telemetryFuel) telemetryFuel.textContent = spec.fuel;
  }

  trimPills.forEach((pill) => {
    pill.addEventListener('click', () => {
      const key = pill.dataset.carKey || 'myvi';
      selectMalaysianCar(key);
    });
  });

  // Left Floating Controls (Motion, LED Headlamp Beam, Jump to Map)
  if (toggleCruiseBtn && showcaseStage) {
    toggleCruiseBtn.addEventListener('click', () => {
      const isCruising = showcaseStage.getAttribute('data-cruise') === 'active';
      const nextState = isCruising ? 'paused' : 'active';
      showcaseStage.setAttribute('data-cruise', nextState);
      toggleCruiseBtn.classList.toggle('active', !isCruising);
      toggleCruiseBtn.setAttribute('aria-pressed', String(!isCruising));
      notify(!isCruising ? 'Eco Cruise motion animation enabled.' : 'Eco Cruise motion paused.');
    });
  }

  if (toggleHeadlampBtn && showcaseStage) {
    toggleHeadlampBtn.addEventListener('click', () => {
      const isBeamOn = showcaseStage.getAttribute('data-beam') === 'on';
      showcaseStage.setAttribute('data-beam', isBeamOn ? 'off' : 'on');
      toggleHeadlampBtn.classList.toggle('active', !isBeamOn);
      toggleHeadlampBtn.setAttribute('aria-pressed', String(!isBeamOn));
    });
  }

  function openAndScrollToMap() {
    if (mapPanel && mapPanel.hidden) {
      mapPanel.hidden = false;
      if (toggleMapBtn) {
        toggleMapBtn.setAttribute('aria-expanded', 'true');
        toggleMapBtn.innerHTML = 'Hide Google Map <span>⌃</span>';
      }
      updateGoogleMapAndRoute(currentOrigin, currentDestination);
    }
    mapContainer?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  if (jumpToMapBtn) jumpToMapBtn.addEventListener('click', openAndScrollToMap);
  if (tileScrollMap) tileScrollMap.addEventListener('click', openAndScrollToMap);
  if (quickFocusMap) quickFocusMap.addEventListener('click', openAndScrollToMap);

  if (tileScrollRides) {
    tileScrollRides.addEventListener('click', () => {
      document.getElementById('availableRidesSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  if (tileFilterCorridors) {
    tileFilterCorridors.addEventListener('click', () => {
      routeSelect?.focus();
      routeSelect?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  }

  // Sync Bento Date/Time Widget with #travelDeparture input
  function updateBentoDateAndSummary() {
    const val = travelDepartureInput ? travelDepartureInput.value : '';
    if (!val) {
      const now = new Date();
      if (bentoDayNumber) bentoDayNumber.textContent = String(now.getDate()).padStart(2, '0');
      if (bentoMonthLabel) bentoMonthLabel.textContent = now.toLocaleDateString('en-MY', { month: 'short' });
      if (bentoTimeValue) bentoTimeValue.textContent = 'ANY';
      if (bentoTimePeriod) bentoTimePeriod.textContent = 'TIME';
      if (bentoDateFullLabel) bentoDateFullLabel.textContent = 'Flexible schedule · Any upcoming time';
    } else {
      const parsed = new Date(val);
      if (!Number.isNaN(parsed.getTime())) {
        if (bentoDayNumber) bentoDayNumber.textContent = String(parsed.getDate()).padStart(2, '0');
        if (bentoMonthLabel) bentoMonthLabel.textContent = parsed.toLocaleDateString('en-MY', { month: 'short' });
        let hours = parsed.getHours();
        const mins = String(parsed.getMinutes()).padStart(2, '0');
        const period = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        if (bentoTimeValue) bentoTimeValue.textContent = `${hours}:${mins}`;
        if (bentoTimePeriod) bentoTimePeriod.textContent = period;
        if (bentoDateFullLabel) {
          bentoDateFullLabel.textContent = parsed.toLocaleDateString('en-MY', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            hour: 'numeric',
            minute: '2-digit'
          });
        }
      }
    }

    // Update live filter summary in floating concierge card & pass card
    const routeText = routeSelect?.selectedOptions?.[0]?.textContent?.trim() || 'All pilot routes';
    const stopRaw = pickupSelect?.selectedOptions?.[0]?.textContent?.trim() || 'Any available stop';
    const stopText = stopRaw.includes('·') ? stopRaw.split('·')[0].trim() : stopRaw;
    const paxCount = Number(passengerSelect?.value || 1);
    const paxText = `${paxCount} ${paxCount === 1 ? 'passenger' : 'passengers'}`;

    if (liveFilterSummaryText) {
      const timeTag = val ? ` · From ${bentoTimeValue?.textContent || ''} ${bentoTimePeriod?.textContent || ''}` : '';
      liveFilterSummaryText.textContent = `${routeText} · ${stopText} · ${paxText}${timeTag}`;
    }

    if (telemetryCorridor) {
      telemetryCorridor.textContent = `📍 ${routeSelect?.value ? routeText : 'Ayer Keroh ↔ UTeM Corridor'}`;
    }

    if (bentoPassMeta) {
      const baseName = bentoPassMeta.textContent.split('·')[0].trim();
      bentoPassMeta.textContent = `${baseName} · ${paxCount} ${paxCount === 1 ? 'seat' : 'seats'} selected`;
    }
  }

  function stepDepartureDay(deltaDays) {
    if (!travelDepartureInput) return;
    const base = travelDepartureInput.value ? new Date(travelDepartureInput.value) : new Date();
    if (Number.isNaN(base.getTime())) return;
    if (!travelDepartureInput.value) {
      base.setHours(8, 0, 0, 0);
    }
    base.setDate(base.getDate() + deltaDays);
    const now = new Date();
    if (base < now) {
      base.setTime(now.getTime() + 15 * 60000);
    }
    travelDepartureInput.value = localDateTimeValue(base);
    timePresetChips.forEach((c) => c.classList.remove('active'));
    updateBentoDateAndSummary();
    applyCurrentFilters(false);
  }

  if (bentoPrevDay) bentoPrevDay.addEventListener('click', () => stepDepartureDay(-1));
  if (bentoNextDay) bentoNextDay.addEventListener('click', () => stepDepartureDay(1));

  if (focusDateTimeBtn && travelDepartureInput) {
    focusDateTimeBtn.addEventListener('click', () => {
      travelDepartureInput.focus();
      if (typeof travelDepartureInput.showPicker === 'function') {
        try {
          travelDepartureInput.showPicker();
        } catch (_) {}
      }
    });
  }

  timePresetChips.forEach((chip) => {
    chip.addEventListener('click', () => {
      timePresetChips.forEach((c) => c.classList.remove('active'));
      chip.classList.add('active');
      const preset = chip.dataset.presetTime || '';
      if (!preset) {
        if (travelDepartureInput) travelDepartureInput.value = '';
      } else if (travelDepartureInput) {
        const [hh, mm] = preset.split(':').map(Number);
        const target = travelDepartureInput.value ? new Date(travelDepartureInput.value) : new Date();
        target.setHours(hh, mm, 0, 0);
        if (target < new Date()) {
          target.setDate(target.getDate() + 1);
        }
        travelDepartureInput.value = localDateTimeValue(target);
      }
      updateBentoDateAndSummary();
      applyCurrentFilters(false);
    });
  });

  if (travelDepartureInput) {
    travelDepartureInput.addEventListener('change', () => {
      timePresetChips.forEach((c) => c.classList.remove('active'));
      updateBentoDateAndSummary();
    });
  }

  if (passengerSelect) {
    passengerSelect.addEventListener('change', () => {
      updateBentoDateAndSummary();
      applyCurrentFilters(false);
    });
  }

  // Update Cost-Sharing Summary from Real Rides Data
  function updateCostSummaryCard(list) {
    if (!bentoCostRangeText) return;
    const source = list.length > 0 ? list : rides;
    if (source.length === 0) {
      bentoCostRangeText.textContent = 'Peer Cost-Sharing (RM / seat)';
      return;
    }
    const costs = source.map((r) => Number(r.cost)).filter((n) => !Number.isNaN(n));
    if (costs.length === 0) return;
    const minCost = Math.min(...costs).toFixed(2);
    const maxCost = Math.max(...costs).toFixed(2);
    bentoCostRangeText.textContent = minCost === maxCost
      ? `RM ${minCost} per passenger seat`
      : `RM ${minCost} – RM ${maxCost} per seat`;
  }

  function resolveDestinationFromSelection() {
    const selectedStopOpt = pickupSelect?.selectedOptions?.[0];
    const selectedRouteOpt = routeSelect?.selectedOptions?.[0];
    const destName = selectedStopOpt?.dataset?.dest || selectedRouteOpt?.dataset?.dest || 'UTeM Main Campus (Durian Tunggal)';
    const coords = window.getStopCoordinates ? window.getStopCoordinates(destName) : { lat: 2.3139, lng: 102.3183, label: destName };
    return {
      lat: coords.lat,
      lng: coords.lng,
      label: destName || coords.label
    };
  }

  function renderGoogleEmbedFallback(lat, lng, label) {
    if (!mapContainer) return;
    mapContainer.innerHTML = '';
    const iframe = document.createElement('iframe');
    iframe.title = `Google Maps - ${label || 'Pickup point'}`;
    iframe.width = '100%';
    iframe.height = '100%';
    iframe.style.border = '0';
    iframe.loading = 'lazy';
    iframe.referrerPolicy = 'no-referrer-when-downgrade';
    iframe.src = `https://www.google.com/maps?q=${encodeURIComponent(`${lat},${lng}`)}&z=15&output=embed`;
    mapContainer.appendChild(iframe);
  }

  async function updateGoogleMapAndRoute(origin, destination) {
    currentOrigin = origin;
    currentDestination = destination || resolveDestinationFromSelection();

    if (pickupLatInput) pickupLatInput.value = Number(currentOrigin.lat).toFixed(6);
    if (pickupLngInput) pickupLngInput.value = Number(currentOrigin.lng).toFixed(6);
    if (pickupPointLabelEl) pickupPointLabelEl.textContent = currentOrigin.label;
    if (pickupCoordsEl) {
      pickupCoordsEl.textContent = `${Number(currentOrigin.lat).toFixed(6)}, ${Number(currentOrigin.lng).toFixed(6)} · Target: ${currentDestination.label}`;
    }

    if (directionsLinkEl && window.buildGoogleMapsDirectionsUrl) {
      directionsLinkEl.href = window.buildGoogleMapsDirectionsUrl(
        currentOrigin.lat,
        currentOrigin.lng,
        currentDestination.lat,
        currentDestination.lng
      );
    }

    // Compute route via Laravel backend -> Google Routes API v2
    let routeData = null;
    if (window.computeGoogleRoute) {
      if (routeMetricsEl) {
        routeMetricsEl.textContent = 'Calculating route via Google Routes API…';
      }
      try {
        routeData = await window.computeGoogleRoute({
          originLat: currentOrigin.lat,
          originLng: currentOrigin.lng,
          originName: currentOrigin.label,
          destLat: currentDestination.lat,
          destLng: currentDestination.lng,
          destName: currentDestination.label
        });
        if (routeMetricsEl && routeData) {
          const sourceBadge = routeData.source === 'google_routes_api' ? 'Google Routes API (Live)' : 'Google Routes Corridor';
          routeMetricsEl.textContent = `${routeData.distance_text} · ~${routeData.duration_text} driving to ${currentDestination.label} (${sourceBadge})`;
          if (conciergeRouteStat) {
            conciergeRouteStat.textContent = `${routeData.distance_text} · ~${routeData.duration_text}`;
          }
        }
      } catch (err) {
        if (routeMetricsEl) {
          routeMetricsEl.textContent = `Pickup ready (${Number(currentOrigin.lat).toFixed(4)}, ${Number(currentOrigin.lng).toFixed(4)}) → ${currentDestination.label}`;
        }
      }
    }

    // Only render the map DOM if the panel is open
    if (!mapPanel || mapPanel.hidden) return;

    if (window.loadGoogleMaps) {
      try {
        const maps = await window.loadGoogleMaps();
        const originPos = { lat: Number(currentOrigin.lat), lng: Number(currentOrigin.lng) };
        const destPos = { lat: Number(currentDestination.lat), lng: Number(currentDestination.lng) };

        if (!googleMapInstance) {
          mapContainer.innerHTML = '';
          googleMapInstance = new maps.Map(mapContainer, {
            center: originPos,
            zoom: 14,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true
          });

          googlePickupMarker = new maps.Marker({
            position: originPos,
            map: googleMapInstance,
            title: currentOrigin.label,
            draggable: true
          });

          googlePickupMarker.addListener('dragend', () => {
            const pos = googlePickupMarker.getPosition();
            if (!pos) return;
            updateGoogleMapAndRoute(
              { lat: pos.lat(), lng: pos.lng(), label: `Pinned pickup (${pos.lat().toFixed(4)}, ${pos.lng().toFixed(4)})` },
              currentDestination
            );
          });

          googleDestMarker = new maps.Marker({
            position: destPos,
            map: googleMapInstance,
            title: currentDestination.label
          });
        } else {
          googlePickupMarker.setPosition(originPos);
          googlePickupMarker.setTitle(currentOrigin.label);
          googleDestMarker.setPosition(destPos);
          googleDestMarker.setTitle(currentDestination.label);
        }

        if (googleRoutePolyline) {
          googleRoutePolyline.setMap(null);
        }

        const pathPoints = Array.isArray(routeData?.path) && routeData.path.length > 1
          ? routeData.path
          : [originPos, destPos];

        googleRoutePolyline = new maps.Polyline({
          path: pathPoints,
          geodesic: true,
          strokeColor: '#1f513a',
          strokeOpacity: 0.92,
          strokeWeight: 4,
          map: googleMapInstance
        });

        const bounds = new maps.LatLngBounds();
        pathPoints.forEach((pt) => bounds.extend(pt));
        googleMapInstance.fitBounds(bounds, 42);
        return;
      } catch (err) {
        // Fallback to Google Maps embed iframe when Maps JS API key isn't active or only Routes API is used
      }
    }

    renderGoogleEmbedFallback(currentOrigin.lat, currentOrigin.lng, currentOrigin.label);
  }

  function updatePickupOptions() {
    const routeId = routeSelect.value;
    [...pickupSelect.options].forEach((option) => {
      if (!option.value) return;
      option.hidden = routeId !== '' && option.dataset.routeId !== routeId;
    });
    if (pickupSelect.selectedOptions[0]?.hidden) pickupSelect.value = '';

    sideCorridorButtons.forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.selectRoute === routeId);
    });

    updateBentoDateAndSummary();
    syncSelectedStopToGoogleMap();
  }

  function syncSelectedStopToGoogleMap() {
    const selectedStopOpt = pickupSelect?.selectedOptions?.[0];
    const selectedRouteOpt = routeSelect?.selectedOptions?.[0];
    const dest = resolveDestinationFromSelection();

    if (selectedStopOpt && selectedStopOpt.value) {
      const stopName = selectedStopOpt.dataset.stopName || selectedStopOpt.textContent.split('·')[0].trim();
      const coords = window.getStopCoordinates ? window.getStopCoordinates(stopName) : currentOrigin;
      updateGoogleMapAndRoute({ lat: coords.lat, lng: coords.lng, label: stopName }, dest);
    } else if (selectedRouteOpt && selectedRouteOpt.value) {
      const originName = selectedRouteOpt.dataset.origin || 'Mydin MITC';
      const coords = window.getStopCoordinates ? window.getStopCoordinates(originName) : currentOrigin;
      updateGoogleMapAndRoute({ lat: coords.lat, lng: coords.lng, label: originName }, dest);
    } else {
      updateGoogleMapAndRoute(currentOrigin, dest);
    }
  }

  routeSelect.addEventListener('change', () => {
    activeDirectionMode = 'all';
    modePills.forEach((p) => p.classList.toggle('active', p.dataset.directionFilter === 'all'));
    updatePickupOptions();
  });

  pickupSelect.addEventListener('change', () => {
    updateBentoDateAndSummary();
    syncSelectedStopToGoogleMap();
  });

  // Top Segmented Corridor Pills (All Corridors / To Campus / From Campus)
  modePills.forEach((pill) => {
    pill.addEventListener('click', () => {
      const dir = pill.dataset.directionFilter || 'all';
      activeDirectionMode = dir;
      modePills.forEach((p) => p.classList.toggle('active', p === pill));

      if (dir === 'all') {
        routeSelect.value = '';
      } else {
        const matchingOption = [...routeSelect.options].find((opt) => opt.dataset.direction === dir);
        if (matchingOption) {
          routeSelect.value = matchingOption.value;
        }
      }
      updatePickupOptions();
      applyCurrentFilters(false);
    });
  });

  // Right-side Active Corridor Clickable Items
  sideCorridorButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const targetRouteId = btn.dataset.selectRoute || '';
      routeSelect.value = routeSelect.value === targetRouteId ? '' : targetRouteId;
      updatePickupOptions();
      applyCurrentFilters(false);
    });
  });

  if (toggleMapBtn && mapPanel) {
    toggleMapBtn.addEventListener('click', () => {
      const isOpen = !mapPanel.hidden;
      mapPanel.hidden = isOpen;
      toggleMapBtn.setAttribute('aria-expanded', String(!isOpen));
      toggleMapBtn.innerHTML = isOpen ? 'Open Google Map <span>⌄</span>' : 'Hide Google Map <span>⌃</span>';
      if (!isOpen) {
        updateGoogleMapAndRoute(currentOrigin, currentDestination);
      }
    });
  }

  function handlePlaceSearch() {
    if (!placeQueryInput) return;
    const query = placeQueryInput.value.trim();
    if (!query) {
      if (searchStatus) searchStatus.textContent = 'Enter a Melaka stop or landmark name to locate it on Google Maps.';
      return;
    }

    if (searchResults) searchResults.innerHTML = '';

    // Match known corridor locations first
    const matches = [];
    if (window.CAMPUS_LOCATIONS) {
      for (const [name, info] of Object.entries(window.CAMPUS_LOCATIONS)) {
        if (name.toLowerCase().includes(query.toLowerCase()) || info.label.toLowerCase().includes(query.toLowerCase())) {
          matches.push({ name, ...info });
        }
      }
    }

    if (matches.length > 0) {
      const top = matches[0];
      if (searchStatus) {
        searchStatus.textContent = `Located "${top.label}" on Google Maps & computed route via Google Routes API.`;
      }
      updateGoogleMapAndRoute({ lat: top.lat, lng: top.lng, label: top.label }, currentDestination);

      if (matches.length > 1 && searchResults) {
        matches.forEach((item) => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'pickup-result-option';
          btn.textContent = `${item.label} (${item.lat.toFixed(4)}, ${item.lng.toFixed(4)})`;
          btn.addEventListener('click', () => {
            updateGoogleMapAndRoute({ lat: item.lat, lng: item.lng, label: item.label }, currentDestination);
          });
          searchResults.appendChild(btn);
        });
      }
      return;
    }

    if (window.google && window.google.maps && window.google.maps.Geocoder) {
      const geocoder = new window.google.maps.Geocoder();
      if (searchStatus) searchStatus.textContent = `Searching Google Maps for "${query}"…`;
      geocoder.geocode({ address: `${query}, Melaka, Malaysia` }, (res, status) => {
        if (status === 'OK' && res && res[0]) {
          const loc = res[0].geometry.location;
          const formatted = res[0].formatted_address || query;
          if (searchStatus) searchStatus.textContent = `Pinned "${formatted}" on Google Maps.`;
          updateGoogleMapAndRoute({ lat: loc.lat(), lng: loc.lng(), label: formatted }, currentDestination);
        } else {
          if (searchStatus) searchStatus.textContent = `Showing "${query}" on Google Maps.`;
          if (mapContainer) {
            mapContainer.innerHTML = `<iframe title="Google Maps Search" width="100%" height="100%" style="border:0" loading="lazy" src="https://www.google.com/maps?q=${encodeURIComponent(query + ', Melaka, Malaysia')}&z=15&output=embed"></iframe>`;
          }
        }
      });
    } else {
      if (searchStatus) searchStatus.textContent = `Showing "${query}, Melaka" on Google Maps.`;
      if (mapContainer) {
        mapContainer.innerHTML = `<iframe title="Google Maps Search" width="100%" height="100%" style="border:0" loading="lazy" src="https://www.google.com/maps?q=${encodeURIComponent(query + ', Melaka, Malaysia')}&z=15&output=embed"></iframe>`;
      }
    }
  }

  if (placeSearchBtn) {
    placeSearchBtn.addEventListener('click', handlePlaceSearch);
  }
  if (placeQueryInput) {
    placeQueryInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        handlePlaceSearch();
      }
    });
  }

  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function detectCarKeyFromVehicleText(vehicleText) {
    const lower = (vehicleText || '').toLowerCase();
    if (lower.includes('bezza') || lower.includes('axia')) return 'bezza';
    if (lower.includes('s70') || lower.includes('saga') || lower.includes('persona')) return 's70';
    if (lower.includes('emas') || lower.includes('ev') || lower.includes('x50') || lower.includes('x70')) return 'emas';
    return 'myvi';
  }

  function makeRideCard(ride, index) {
    const card = element('article', 'find-ride-card');
    card.style.animationDelay = `${Math.min(index * 60, 360)}ms`;

    const top = element('div', 'find-ride-top');
    const driver = element('div', 'find-driver');
    driver.append(element('span', 'find-driver-avatar', ride.initials || 'D'));

    const driverName = element('span');
    driverName.append(element('b', '', ride.driver));

    const driverMeta = element('small', '');
    driverMeta.append(document.createTextNode('Verified UTeM Driver'));
    const vehicleLabel = [ride.vehicleColor, ride.vehicleType].filter(Boolean).join(' ');
    if (vehicleLabel) {
      const vBadge = element('span', 'ride-vehicle-badge', `🚗 ${vehicleLabel}`);
      driverMeta.append(document.createTextNode(' · '), vBadge);
    }
    driverName.append(driverMeta);
    driver.append(driverName);

    const cost = element('div', 'find-ride-cost');
    cost.append(element('b', '', `RM ${ride.cost}`), element('small', '', 'per passenger seat'));
    top.append(driver, cost);

    const route = element('div', 'find-route-summary');
    const departure = element('div', 'find-departure');
    const dateText = new Date(`${ride.departureDate}T00:00:00`).toLocaleDateString('en-MY', {
      day: 'numeric',
      month: 'short',
      year: 'numeric'
    });
    departure.append(element('b', '', ride.time), element('small', '', dateText));

    const points = element('div', 'find-route-points');
    const line = element('span', 'find-route-line');
    line.setAttribute('aria-hidden', 'true');
    line.append(document.createElement('i'), document.createElement('i'));

    const labels = element('span', 'find-route-labels');
    labels.append(element('b', '', ride.pickup), element('small', '', ride.destination));
    points.append(line, labels);
    route.append(departure, points, element('span', 'find-seat-pill', `${ride.seats} seat${ride.seats === 1 ? '' : 's'} open`));

    const stopList = element('div', 'ride-fixed-stops');
    stopList.append(element('b', '', 'Fixed pickup stops:'));
    const stopNames = ride.pickupStops.map((stop) => stop.name).join(' · ');
    stopList.append(element('span', '', stopNames || ride.pickup));

    const detailsButton = element('button', 'find-details-button', 'View trip details');
    detailsButton.type = 'button';
    detailsButton.setAttribute('aria-expanded', 'false');
    detailsButton.setAttribute('aria-controls', `ride-detail-${index}`);

    const mapPreviewButton = element('button', 'find-map-preview-btn', '⌖ Preview on Map');
    mapPreviewButton.type = 'button';
    mapPreviewButton.addEventListener('click', () => {
      const originCoords = window.getStopCoordinates ? window.getStopCoordinates(ride.pickup) : currentOrigin;
      const destCoords = window.getStopCoordinates ? window.getStopCoordinates(ride.destination) : currentDestination;
      updateGoogleMapAndRoute(
        { lat: originCoords.lat, lng: originCoords.lng, label: ride.pickup },
        { lat: destCoords.lat, lng: destCoords.lng, label: ride.destination }
      );
      if (vehicleLabel) {
        const key = detectCarKeyFromVehicleText(vehicleLabel);
        selectMalaysianCar(key, {
          tag: `${ride.routeName || 'Campus Corridor'} · ${ride.seats} seats open`,
          title: vehicleLabel,
          subtitle: `Driven by ${ride.driver} · Departs ${ride.time} (${dateText})`,
          fuel: `💳 RM ${ride.cost} / seat`
        });
      }
      openAndScrollToMap();
      notify(`Previewing ${ride.driver}'s route (${ride.pickup} → ${ride.destination}) on Google Maps.`);
    });

    let requestAction;
    if (ride.bookingStatus === 'pending' || ride.bookingStatus === 'accepted') {
      requestAction = element(
        'button',
        'find-request-button',
        ride.bookingStatus === 'pending' ? 'Request pending' : 'Seat confirmed'
      );
      requestAction.type = 'button';
      requestAction.disabled = true;
    } else {
      requestAction = document.createElement('form');
      requestAction.method = 'post';
      requestAction.className = 'find-request-form';
      const hidden = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        return input;
      };
      const latField = hidden('pickup_latitude', pickupLatInput ? pickupLatInput.value : '2.270700');
      const lngField = hidden('pickup_longitude', pickupLngInput ? pickupLngInput.value : '102.285600');
      requestAction.append(
        hidden('_token', window.bookingCsrf || ''),
        hidden('csrf_token', window.bookingCsrf || ''),
        hidden('action', 'request_seat'),
        hidden('ride_id', ride.id),
        latField,
        lngField
      );
      const stopChoice = document.createElement('select');
      stopChoice.name = 'pickup_stop_id';
      stopChoice.required = true;
      stopChoice.setAttribute('aria-label', 'Choose your fixed pickup stop');
      const firstStop = document.createElement('option');
      firstStop.value = '';
      firstStop.disabled = true;
      firstStop.selected = true;
      firstStop.textContent = 'Choose pickup stop';
      stopChoice.append(firstStop);
      ride.pickupStops.forEach((stop) => {
        const option = document.createElement('option');
        option.value = String(stop.id);
        option.textContent = stop.name;
        option.dataset.stopName = stop.name;
        stopChoice.append(option);
      });

      stopChoice.addEventListener('change', () => {
        const chosenName = stopChoice.selectedOptions[0]?.dataset?.stopName || '';
        if (chosenName && window.getStopCoordinates) {
          const coords = window.getStopCoordinates(chosenName);
          latField.value = Number(coords.lat).toFixed(6);
          lngField.value = Number(coords.lng).toFixed(6);
          const destCoords = window.getStopCoordinates(ride.destination);
          updateGoogleMapAndRoute(
            { lat: coords.lat, lng: coords.lng, label: chosenName },
            { lat: destCoords.lat, lng: destCoords.lng, label: ride.destination }
          );
        }
      });

      const seatCount = document.createElement('select');
      seatCount.name = 'seats_requested';
      seatCount.setAttribute('aria-label', 'Seats to request');
      for (let seat = 1; seat <= Math.min(3, ride.seats); seat++) {
        const option = document.createElement('option');
        option.value = String(seat);
        option.textContent = `${seat} seat${seat === 1 ? '' : 's'}`;
        seatCount.append(option);
      }
      const requestButton = element('button', 'find-request-button', 'Request seat');
      requestButton.type = 'submit';
      requestAction.append(stopChoice, seatCount, requestButton);
    }

    const leftBtns = element('div', 'ride-left-btns');
    leftBtns.append(mapPreviewButton, detailsButton);

    const actions = element('div', 'find-ride-actions');
    actions.append(leftBtns, requestAction);

    const extra = element('div', 'find-extra');
    extra.id = `ride-detail-${index}`;
    extra.hidden = true;
    const detailParts = [
      `Route: ${ride.routeName}`,
      vehicleLabel ? `Vehicle: ${vehicleLabel}` : '',
      ride.notes ? `Driver note: ${ride.notes}` : ''
    ].filter(Boolean);
    extra.textContent = detailParts.join(' · ');

    detailsButton.addEventListener('click', () => {
      const expanded = detailsButton.getAttribute('aria-expanded') === 'true';
      detailsButton.setAttribute('aria-expanded', String(!expanded));
      extra.hidden = expanded;
      detailsButton.textContent = expanded ? 'View trip details' : 'Hide trip details';
    });

    card.append(top, route, stopList, actions, extra);
    return card;
  }

  function renderRides(filteredRides) {
    results.replaceChildren(...filteredRides.map(makeRideCard));
    empty.hidden = filteredRides.length > 0;
    const countLabel = `${filteredRides.length} ${filteredRides.length === 1 ? 'trip' : 'trips'}`;
    count.textContent = countLabel;
    if (conciergeRidesStat) {
      conciergeRidesStat.textContent = `${countLabel} matching`;
    }
    updateCostSummaryCard(filteredRides);

    if (rides.length === 0) {
      empty.querySelector('b').textContent = 'No pilot-route rides yet';
      empty.querySelector('p').textContent = 'When an approved driver publishes a scheduled ride on this fixed corridor, it will appear here.';
      if (emptyResetFiltersBtn) emptyResetFiltersBtn.hidden = true;
    } else if (filteredRides.length === 0) {
      empty.querySelector('b').textContent = 'No rides match those filters';
      empty.querySelector('p').textContent = 'Try another route, pickup stop, departure date or time, or passenger count.';
      if (emptyResetFiltersBtn) emptyResetFiltersBtn.hidden = false;
    }
  }

  function applyCurrentFilters(shouldScroll = true) {
    const routeId = routeSelect.value;
    const stopId = pickupSelect.value;
    const selectedDeparture = travelDepartureInput.value;
    const passengers = Number(passengerSelect?.value || 1);

    if (travelDepartureInput && travelDepartureInput.validity.rangeUnderflow) {
      notify('Choose a departure date and time that have not passed.');
      return;
    }

    const matches = rides.filter((ride) =>
      ride.seats >= passengers &&
      (!routeId || String(ride.routeId) === routeId) &&
      (activeDirectionMode === 'all' || !ride.direction || ride.direction === activeDirectionMode) &&
      (!stopId || ride.pickupStops.some((stop) => String(stop.id) === stopId)) &&
      (!selectedDeparture || ride.departureAt >= selectedDeparture)
    );

    renderRides(matches);
    if (shouldScroll) {
      results.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      if (matches.length === 0) {
        notify(
          rides.length === 0
            ? 'There are no scheduled pilot-route rides yet.'
            : 'No rides match your search. Try changing the route, stop, time, or passenger count.'
        );
      }
    }
  }

  function resetAllFilters() {
    routeSelect.value = '';
    pickupSelect.value = '';
    if (travelDepartureInput) travelDepartureInput.value = '';
    if (passengerSelect) passengerSelect.value = '1';
    activeDirectionMode = 'all';
    modePills.forEach((p) => p.classList.toggle('active', p.dataset.directionFilter === 'all'));
    timePresetChips.forEach((c) => c.classList.toggle('active', c.dataset.presetTime === ''));
    updatePickupOptions();
    renderRides(rides);
    notify('All ride search filters reset.');
  }

  if (quickResetFilters) quickResetFilters.addEventListener('click', resetAllFilters);
  if (emptyResetFiltersBtn) emptyResetFiltersBtn.addEventListener('click', resetAllFilters);

  if (quickMorningSlot) {
    quickMorningSlot.addEventListener('click', () => {
      const target = new Date();
      target.setHours(8, 0, 0, 0);
      if (target < new Date()) {
        target.setDate(target.getDate() + 1);
      }
      if (travelDepartureInput) {
        travelDepartureInput.value = localDateTimeValue(target);
      }
      timePresetChips.forEach((c) => c.classList.toggle('active', c.dataset.presetTime === '08:00'));
      updateBentoDateAndSummary();
      applyCurrentFilters(false);
      notify('Set departure filter to next 8:00 AM campus commute.');
    });
  }

  if (quickTriggerSearch) {
    quickTriggerSearch.addEventListener('click', () => {
      applyCurrentFilters(true);
    });
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    applyCurrentFilters(true);
  });

  // Initial setup
  updatePickupOptions();
  renderRides(rides);
})();
