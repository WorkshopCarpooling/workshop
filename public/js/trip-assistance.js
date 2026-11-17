(() => {
  'use strict';

  const statusLabels = {
    waiting: 'Report sent to campus admin. Awaiting review.',
    en_route: 'Admin has prepared an assistance route. Contact campus admin for updates.',
    arrived: 'Admin has prepared an assistance route. Contact campus admin for updates.',
    resolved: 'Assistance report closed.',
  };
  const connectionError = 'Could not check the assistance status. Retrying…';

  const element = (tag, text, className) => {
    const node = document.createElement(tag);
    node.textContent = text;
    if (className) node.className = className;
    return node;
  };

  async function request(url, options = {}) {
    const response = await fetch(url, {
      cache: 'no-store',
      credentials: 'same-origin',
      signal: AbortSignal.timeout(10000),
      ...options,
      headers: { Accept: 'application/json', ...options.headers },
    });
    const data = await response.json();
    if (!response.ok) {
      const error = new Error(data.message || 'The assistance request could not be saved.');
      error.status = response.status;
      throw error;
    }
    return data;
  }

  const accessEnded = (error) => [401, 403, 404, 419].includes(error.status);
  const adminNotice = document.getElementById('admin-assistance-notice');
  if (adminNotice) {
    const label = adminNotice.querySelector('[role="status"]');
    async function pollNotice() {
      try {
        const { reports } = await request(adminNotice.dataset.feedUrl);
        adminNotice.hidden = reports.length === 0;
        const message = reports.length === 1 ? '1 open assistance report.' : `${reports.length} open assistance reports.`;
        if (label.textContent !== message) label.textContent = message;
      } catch (error) {
        adminNotice.hidden = true;
        if (accessEnded(error)) return;
      }
      setTimeout(pollNotice, 5000);
    }
    void pollNotice();
  }

  const tripPanel = document.getElementById('trip-assistance');
  if (tripPanel) {
    const form = tripPanel.querySelector('form');
    const dialog = tripPanel.querySelector('dialog');
    const open = tripPanel.querySelector('[data-assistance-open]');
    const close = tripPanel.querySelector('[data-assistance-close]');
    const feedback = tripPanel.querySelector('[data-assistance-feedback]');
    const status = tripPanel.querySelector('[data-assistance-status]');
    const submit = form?.querySelector('[type="submit"]');
    let saving = false;
    let version = 0;

    function showTripStatus(data) {
      const message = statusLabels[data.status] || '';
      if (status.textContent !== message) status.textContent = message;
      if (open) {
        open.hidden = Boolean(data.status && data.status !== 'resolved');
        if (open.hidden && dialog.open) {
          dialog.close();
          form.reset();
          status.focus();
        }
      }
      tripPanel.hidden = !form && !data.status;
    }

    open?.addEventListener('click', () => {
      feedback.textContent = '';
      dialog.showModal();
    });
    close?.addEventListener('click', () => dialog.close());
    dialog?.addEventListener('cancel', (event) => {
      if (saving) event.preventDefault();
    });

    async function pollTrip() {
      const current = version;
      if (!saving) {
        try {
          const data = await request(tripPanel.dataset.feedUrl);
          if (!saving && current === version) showTripStatus(data);
        } catch (error) {
          if (!saving && current === version) {
            if (accessEnded(error)) {
              if (dialog?.open) dialog.close();
              tripPanel.hidden = true;
              return;
            }
            if (!tripPanel.hidden) status.textContent = connectionError;
          }
        }
      }
      setTimeout(pollTrip, 5000);
    }

    form?.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (saving) return;
      saving = true;
      version++;
      submit.disabled = true;
      close.disabled = true;
      feedback.textContent = '';
      submit.textContent = 'Sending report…';
      try {
        showTripStatus(await request(form.action, { method: 'POST', body: new FormData(form) }));
      } catch (error) {
        feedback.textContent = error.status ? error.message : 'Could not send the report. Please try again.';
      } finally {
        submit.disabled = false;
        submit.textContent = 'Send report';
        close.disabled = false;
        saving = false;
      }
    });
    void pollTrip();
  }

  function admin(map, L, mechanicIcon, stopFollowing) {
    const panel = document.getElementById('admin-assistance');
    if (!panel) return;
    const feed = panel.dataset.feedUrl;
    const status = panel.querySelector('[data-assistance-status]');
    const list = panel.querySelector('[data-assistance-reports]');
    let reports = [];
    let workshop;
    let selectedId = null;
    let layers = [];
    let drawing = '';
    let visibleReports = '';
    let saving = false;
    let version = 0;

    function reportedCar(point) {
      const box = element('span', '');
      box.append(element('b', '🚘'), document.createTextNode('Reported car'));
      return L.marker(point, {
        interactive: false,
        icon: L.divIcon({
          html: box,
          className: 'assistance-map-marker',
          iconSize: [112, 55],
          iconAnchor: [54, 32],
        }),
        zIndexOffset: 1000,
      }).addTo(map);
    }

    function drawAssistanceRoute(report) {
      const coords = [workshop, ...(report.route?.path ?? []).map(point => [point.lat, point.lng]), report.position];
      layers.push(L.polyline(coords, {
        color: '#275f93', weight: 4, dashArray: report.route?.road ? null : '6 7', interactive: false,
      }).addTo(map));

      const icon = mechanicIcon();
      icon.querySelector('.car-state-label').textContent = 'Workshop';
      layers.push(L.marker(workshop, {
        interactive: false,
        zIndexOffset: 1100,
        icon: L.divIcon({
          html: icon, className: 'live-car-marker assistance-mechanic-marker', iconSize: [64, 88], iconAnchor: [32, 44],
        }),
      }).addTo(map));

      return coords;
    }

    function draw(report, focus = false) {
      if (!map) return;
      const key = report ? JSON.stringify([report.id, report.position, report.dispatchedAt]) : '';
      if (!focus && key === drawing) return;
      drawing = key;
      layers.forEach((layer) => layer.remove());
      layers = [];
      if (!report?.position) return;

      layers.push(reportedCar(report.position));
      const route = report.dispatchedAt ? drawAssistanceRoute(report) : null;
      if (focus) {
        stopFollowing();
        if (route) map.fitBounds(L.latLngBounds(route), { padding: [75, 75], maxZoom: 16 });
        else map.setView(report.position, 16);
        document.getElementById('admin-driver-map')?.scrollIntoView({ block: 'center' });
      }
    }

    function renderReports() {
      const snapshot = JSON.stringify(reports);
      if (snapshot === visibleReports) return;
      visibleReports = snapshot;
      const focused = document.activeElement?.dataset?.assistanceAction;
      list.replaceChildren();
      for (const report of reports) {
        const card = element('article', '', 'assistance-report');
        card.append(element('b', `${report.reason} · Trip #${report.rideId}`));
        card.append(element('small', [report.driver, report.plate].filter(Boolean).join(' · ')));
        card.append(element('small', report.dispatchedAt ? 'Assistance route prepared' : 'Awaiting review'));
        if (report.dispatchedAt && !report.route?.road) card.append(element('small', 'Approximate route'));
        card.append(element('small', report.position
          ? `${report.positionSource === 'gps' ? 'Reported GPS position' : 'Estimated trip position'} · ${new Date(report.positionAt).toLocaleTimeString('en-MY')}`
          : 'A trip location is needed to plan an assistance route.'));
        const actions = element('div', '', 'assistance-actions');
        const button = (label, action, handler, disabled = false) => {
          const control = element('button', label, action === 'dispatch' ? 'solid-button' : 'outline-button');
          control.type = 'button';
          control.disabled = disabled;
          control.dataset.assistanceAction = `${report.id}-${action}`;
          control.addEventListener('click', handler);
          actions.append(control);
        };
        button('Show on map', 'show', () => {
          selectedId = report.id;
          draw(report, true);
        }, !report.position || !map);
        if (report.status === 'waiting') {
          button('Plan assistance route', 'dispatch', (event) => update(report.id, 'dispatch', event.currentTarget), !report.position);
        }
        button('Close report', 'resolve', (event) => update(report.id, 'resolve', event.currentTarget));
        card.append(actions);
        list.append(card);
      }
      if (focused) list.querySelector(`[data-assistance-action="${focused}"]`)?.focus({ preventScroll: true });
    }

    function apply(data, focusId = null) {
      reports = data.reports;
      workshop = data.workshop;
      selectedId = focusId ?? selectedId;
      const selected = reports.find((report) => report.id === selectedId);
      if (!selected) selectedId = null;
      draw(selected, focusId !== null);
      renderReports();
      const message = reports.length === 0 ? 'No open reports.'
        : reports.length === 1 ? '1 open assistance report.' : `${reports.length} open assistance reports.`;
      if (status.textContent !== message) status.textContent = message;
    }

    async function update(id, action, control) {
      if (saving) return;
      saving = true;
      version++;
      control.disabled = true;
      if (action === 'dispatch') status.textContent = 'Finding an assistance route…';
      try {
        const data = await request(`${feed}/${id}`, {
          method: 'POST',
          signal: AbortSignal.timeout(15000),
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': panel.dataset.csrf },
          body: JSON.stringify({ action }),
        });
        apply(data, action === 'dispatch' ? id : null);
      } catch (error) {
        status.textContent = error.message;
      } finally {
        control.disabled = false;
        saving = false;
      }
    }

    async function pollAdmin() {
      const current = version;
      if (!saving) {
        try {
          const data = await request(feed);
          if (!saving && current === version) apply(data);
        } catch (error) {
          if (accessEnded(error)) {
            draw(null);
            list.replaceChildren();
            status.textContent = 'Log in again to see assistance reports.';
            return;
          }
          if (!saving && current === version) status.textContent = connectionError;
        }
      }
      setTimeout(pollAdmin, 5000);
    }

    void pollAdmin();
  }

  window.TripAssistance = { admin };
})();
