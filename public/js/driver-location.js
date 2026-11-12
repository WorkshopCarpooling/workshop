(() => {
  document.querySelectorAll('[data-location-url]').forEach((panel) => {
    const startButton = panel.querySelector('[data-share-start]');
    const stopButton = panel.querySelector('[data-share-stop]');
    const status = panel.querySelector('[data-share-status]');
    const base = panel.dataset.locationUrl;
    let token = null, watch = null, timer = null, busy = false, pending = null, lastSent = 0, running = false, generation = 0;
    let reportedState = null, paused = false;
    const csrf = window.bookingCsrf || '';
    const request = async (path, method, data, keepalive = false) => {
      const response = await fetch(base + path, {
        method, credentials: 'same-origin', keepalive, signal: AbortSignal.timeout(10000),
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Tracking-Token': token || '' },
        ...(data ? { body: JSON.stringify(data) } : {})
      });
      if (!response.ok) {
        const error = new Error(`Location request failed (${response.status}).`);
        error.status = response.status;
        throw error;
      }
      return response.json();
    };
    const reportState = async (state) => {
      if (!token || reportedState === state) return;
      try { await request('/tracking', 'POST', {state}); reportedState = state; } catch (error) { /* GPS status retries on the next event. */ }
    };
    const clearLocal = () => {
      running = false;
      generation++;
      if (watch !== null) navigator.geolocation.clearWatch(watch);
      watch = null;
      clearInterval(timer);
      timer = null;
      pending = null;
    };
    const stop = async (message = 'Location sharing is off.') => {
      paused = true;
      clearLocal();
      startButton.disabled = true;
      stopButton.disabled = true;
      try {
        if (token) await request('/location', 'DELETE');
        token = null;
        startButton.hidden = false;
        stopButton.hidden = true;
        status.textContent = message;
      } catch (error) {
        if ([401, 403, 419].includes(error.status)) {
          token = null;
          stopButton.hidden = true;
          startButton.hidden = false;
          status.textContent = 'Driver session ended. Sign in as the driver again. Keep admin in a separate browser or private session while testing live GPS.';
        } else if ([404, 409].includes(error.status)) {
          token = null;
          stopButton.hidden = true;
          startButton.hidden = false;
          status.textContent = 'GPS stopped on this page. Sharing ended or your session changed. Reload before sharing again.';
        } else {
          status.textContent = 'GPS stopped on this page. Could not confirm removal of the last location; retry Stop sharing. It expires after 10 minutes.';
        }
      } finally {
        startButton.disabled = !!token;
        stopButton.disabled = false;
      }
    };
    const send = async () => {
      if (!running || busy || !pending || Date.now() - lastSent < 5000) return;
      if (Date.now() - pending.capturedAt * 1000 > 60000) {
        pending = null;
        status.textContent = 'Waiting for a fresh GPS fix. Passengers and admin see the last known location.';
        return;
      }
      busy = true;
      const fix = pending;
      const current = generation;
      pending = null;
      lastSent = Date.now();
      try {
        await request('/location', 'POST', fix);
        if (running && current === generation) status.textContent = `Sharing with confirmed passengers and admin · updated ${new Date().toLocaleTimeString()} · GPS accuracy ±${Math.round(fix.accuracy)} m.`;
      } catch (error) {
        if (!running || current !== generation) return;
        if ([401, 403, 404, 409, 419, 429].includes(error.status)) await stop('Sharing ended. Reload to check your trip and session.');
        else status.textContent = error.status === 422 ? 'GPS fix rejected. Waiting for a fresh fix; check the device clock.' : 'Connection interrupted. Retrying with the next GPS fix; passengers and admin see the last known location.';
      } finally { busy = false; }
    };
    const startSharing = async () => {
      if (running || startButton.disabled) return;
      paused = false;
      startButton.disabled = true;
      status.textContent = 'Connecting… Allow location access when your browser asks.';
      const current = ++generation;
      try {
        const result = await request('/tracking/start', 'POST');
        if (current !== generation) return;
        token = result.token;
        reportedState = null;
        running = true;
        startButton.hidden = true;
        stopButton.hidden = false;
        if (!window.isSecureContext || !navigator.geolocation) {
          running = false;
          startButton.hidden = false;
          startButton.disabled = false;
          stopButton.hidden = true;
          const secure = !!window.isSecureContext;
          status.textContent = secure ? 'This browser does not support GPS. Use a browser with location support.' : 'Browser blocks GPS on plain HTTP. On this computer open http://localhost/workshop/driver.php; workshop.test or phones need HTTPS.';
          const localLink = panel.querySelector('[data-gps-localhost]');
          if (localLink) localLink.hidden = secure;
          await reportState(secure ? 'unsupported' : 'insecure_context');
          return;
        }
        status.textContent = 'Waiting for GPS permission and a fresh location…';
        const onPosition = (position) => {
          if (!running || current !== generation) return;
          reportedState = 'live';
          pending = { latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy: position.coords.accuracy, heading: Number.isFinite(position.coords.heading) ? position.coords.heading : null, capturedAt: position.timestamp / 1000 };
          void send();
        };
        const onError = (error) => {
          if (!running || current !== generation) return;
          if (error.code === 1) {
            clearLocal();
            startButton.hidden = false;
            startButton.disabled = false;
            stopButton.hidden = true;
            status.textContent = 'Location permission denied by your browser. Allow location in site settings, then retry GPS. Admin sees the GPS error.';
            void reportState('permission_denied');
          } else {
            status.textContent = 'GPS is temporarily unavailable. Waiting for a new fix; passengers and admin see the last known location.';
            void reportState('unavailable');
          }
        };
        const options = { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 };
        watch = navigator.geolocation.watchPosition(onPosition, onError, options);
        let tick = 0, locating = false;
        timer = setInterval(async () => {
          void send();
          if (++tick % 2 !== 0 || !running) return;
          // Stationary phones may not trigger watchPosition; request a fresh fix too.
          if (!locating) {
            locating = true;
            navigator.geolocation.getCurrentPosition((position) => { locating = false; onPosition(position); }, (error) => { locating = false; onError(error); }, options);
          }
          try {
            const state = await request('/tracking', 'GET');
            if (running && current === generation && (!state.active || !state.sharing)) await stop('Sharing ended. The trip finished or sharing changed in another tab.');
          } catch (error) {
            if (running && current === generation && [401, 403, 404, 419].includes(error.status)) await stop('Your session ended. GPS sharing stopped.');
          }
        }, 5000);
      } catch (error) {
        status.textContent = [401, 403, 419].includes(error.status)
          ? 'Driver session ended. Sign in as the driver again and use a separate browser or private session for admin.'
          : 'Could not start sharing. Check your connection and that the trip is still active, then retry.';
        startButton.disabled = false;
      }
    };
    startButton.addEventListener('click', startSharing);
    // Panels exist only for the signed-in driver's in-progress trips.
    void startSharing();
    stopButton.addEventListener('click', () => void stop());
    window.addEventListener('pagehide', () => {
      const wasRunning = running;
      clearLocal();
      if (token && wasRunning) void request('/tracking', 'POST', {state:'page_closed'}, true).catch(() => {});
    });
    window.addEventListener('pageshow', (event) => {
      if (event.persisted && !paused) { startButton.disabled = false; void startSharing(); }
    });
  });
})();
