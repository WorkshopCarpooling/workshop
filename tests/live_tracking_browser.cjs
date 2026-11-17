const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
let checks = 0;
const check = (value, label) => { assert.ok(value, label); checks++; console.log(`PASS: ${label}`); };
const settle = async () => { for (let i = 0; i < 8; i++) await new Promise(setImmediate); };
class Element {
  constructor() { this.textContent = ''; this.children = []; this.listeners = {}; this.dataset = {}; this.style = {}; this.hidden = false; this.disabled = false; this.classes = new Set(); this.classList = { toggle: (name, active) => active ? this.classes.add(name) : this.classes.delete(name) }; }
  append(...children) { this.children.push(...children); }
  replaceChildren(...children) { this.children = children; }
  addEventListener(name, callback) { this.listeners[name] = callback; }
  querySelector() { return null; }
}
const text = (node) => node.textContent + node.children.map(text).join(' ');
function driverHarness({ secure = true, active = true } = {}) {
  const start = new Element(), stop = new Element(), status = new Element(), panel = new Element();
  panel.dataset.locationUrl = '/api/driver/rides/1';
  panel.querySelector = (selector) => ({ '[data-share-start]': start, '[data-share-stop]': stop, '[data-share-status]': status })[selector];
  const requests = [], intervals = [], windows = {}, callbacks = {};
  let now = 1900000000000, cleared = 0, failure = 0;
  const geo = {
    watchPosition: (success, error) => { callbacks.success = success; callbacks.error = error; return 1; },
    clearWatch: () => { cleared++; },
    getCurrentPosition: (success) => success({ coords: { latitude: 2.31, longitude: 102.31, accuracy: 10, heading: null }, timestamp: now })
  };
  const context = { document: { querySelectorAll: () => active ? [panel] : [] }, navigator: { geolocation: geo },
    window: { isSecureContext: secure, bookingCsrf: 'synthetic', addEventListener: (name, callback) => { windows[name] = callback; } },
    AbortSignal, Date: class extends Date { static now() { return now; } },
    setInterval: (fn) => { intervals.push(fn); return fn; }, clearInterval: (fn) => { const i = intervals.indexOf(fn); if (i >= 0) intervals.splice(i, 1); },
    fetch: async (url, options) => { requests.push({ url, ...options }); const status = failure || 200; return { ok: status === 200, status, json: async () => url.endsWith('/start') ? { token: 'synthetic-lease' } : { active: true, sharing: true } }; }
  };
  vm.runInNewContext(fs.readFileSync('public/js/driver-location.js', 'utf8'), context);
  return { start, stop, status, requests, callbacks, windows, context, intervals, advance: (ms) => { now += ms; }, now: () => now, cleared: () => cleared, fail: (value) => { failure = value; } };
}
function adminHarness({ reducedMotion = false, leaflet = true } = {}) {
  const nodes = Object.fromEntries(['live-drivers','tracking-driver-list','tracking-feed-status','live-driver-count','admin-driver-map','tracking-fit'].map((id) => [id, new Element()]));
  nodes['live-drivers'].dataset.feedUrl = '/api/admin/driver-locations';
  nodes['live-drivers'].dataset.carIconUrl = '/images/tracking-car-pixel.svg';
  const intervals = [], timeouts = [], frames = new Map(), allMarkers = [], responses = [];
  let now = 0, frameId = 0;
  const map = { setView() { return this; }, on() {}, fitBounds() {}, panTo() {} };
  const L = {
    map: () => map, tileLayer: () => ({ addTo() {} }), divIcon: (options) => options, latLngBounds: (points) => points,
    marker: (point) => { const markerElement = new Element(), car = new Element(); markerElement.querySelector = () => car;
      const marker = { point: [...point], removed: false, getLatLng() { return { lat: this.point[0], lng: this.point[1] }; }, setLatLng(point) { this.point = [...point]; }, addTo() { return this; }, bindTooltip() {}, openTooltip() {}, getElement: () => markerElement, remove() { this.removed = true; } };
      allMarkers.push(marker); return marker;
    },
    circle: () => ({ addTo() { return this; }, setLatLng() {}, setRadius() {}, setStyle() {}, remove() {} })
  };
  const context = { document: { getElementById: (id) => nodes[id], createElement: () => new Element(), activeElement: null },
    window: { L: leaflet ? L : null, matchMedia: () => ({ matches: reducedMotion }) }, L, performance: { now: () => now }, Date, AbortSignal,
    requestAnimationFrame: (fn) => { frames.set(++frameId, fn); return frameId; }, cancelAnimationFrame: (id) => frames.delete(id),
    setInterval: (fn) => { intervals.push(fn); }, setTimeout: (fn) => { timeouts.push(fn); },
    fetch: async () => { const result = responses.shift() || { rides: [] }; if (result instanceof Error) throw result; return { ok: !result.status, status: result.status || 200, json: async () => result }; }
  };
  responses.push({ rides: [] });
  vm.runInNewContext(fs.readFileSync('public/js/admin-live-tracking.js', 'utf8'), context);
  return { nodes, allMarkers, frames, responses, intervals, advance: (ms) => { now += ms; }, tick: () => intervals.forEach((fn) => fn()), poll: async (result) => { responses.push(result); timeouts.shift()(); await settle(); }, frame: () => { const pending = [...frames.values()]; frames.clear(); pending.forEach((fn) => fn(now)); } };
}
(async () => {
  const driver = driverHarness();
  await settle();
  check(driver.callbacks.success && driver.requests[0].url.endsWith('/tracking/start'), 'an active trip starts GPS automatically without clicking Share location');
  const inactive = driverHarness({active:false}); await settle();
  check(inactive.requests.length === 0, 'pages without active trips do not start GPS');
  driver.callbacks.success({ coords: { latitude: 2.31, longitude: 102.31, accuracy: 8, heading: 90 }, timestamp: driver.now() });
  await settle();
  const upload = driver.requests.find((request) => request.url.endsWith('/location'));
  check(JSON.parse(upload.body).latitude === 2.31 && upload.headers['X-Tracking-Token'] === 'synthetic-lease', 'GPS uploads contain real callback coordinates, CSRF and the sharing lease');
  const before = driver.requests.length;
  driver.callbacks.success({ coords: { latitude: 2.32, longitude: 102.32, accuracy: 8 }, timestamp: driver.now() });
  await settle();
  check(driver.requests.length === before, 'rapid GPS callbacks do not flood the server');
  driver.advance(5000); await driver.intervals[0](); await settle();
  check(driver.requests.length === before + 1, 'the next upload uses the most recent pending GPS fix');
  driver.advance(5000); await driver.intervals[0](); await settle();
  check(driver.requests.filter((request) => request.url.endsWith('/location')).length === 3, 'stationary phones request fresh GPS fixes too');
  driver.stop.listeners.click(); await settle();
  check(driver.cleared() === 1 && driver.requests.at(-1).method === 'DELETE' && /sharing is off/.test(driver.status.textContent), 'Stop sharing clears GPS and revokes the server lease');
  const stoppedRequests = driver.requests.length;
  driver.callbacks.success({ coords: { latitude: 3, longitude: 103, accuracy: 9 }, timestamp: driver.now() }); await settle();
  check(driver.requests.length === stoppedRequests, 'late GPS callbacks cannot restart sharing');
  const insecure = driverHarness({secure:false}); await settle();
  check(!insecure.callbacks.success && insecure.requests.some(request => request.body && JSON.parse(request.body).state==='insecure_context') && /HTTPS/.test(insecure.status.textContent), 'plain HTTP reports its GPS block to admin and explains how to use localhost');
  const denied = driverHarness(); await settle(); denied.callbacks.error({ code: 1 }); await settle();
  check(denied.cleared() === 1 && /permission denied/.test(denied.status.textContent), 'permission denial stops the watch and explains the recovery');
  const interrupted = driverHarness(); await settle(); interrupted.fail(409);
  interrupted.callbacks.success({ coords: { latitude: 2.31, longitude: 102.31, accuracy: 8 }, timestamp: interrupted.now() }); await settle();
  check(interrupted.cleared() === 1 && interrupted.intervals.length === 0, 'a retired sharing lease stops GPS and polling');
  const closing = driverHarness(); await settle(); closing.windows.pagehide(); await settle();
  check(closing.cleared() === 1 && closing.requests.at(-1).keepalive && JSON.parse(closing.requests.at(-1).body).state === 'page_closed', 'leaving the page stops GPS but retains a last-known car through keepalive status');

  const admin = adminHarness(); await settle();
  check(admin.allMarkers.length === 0 && /No active student trips/.test(text(admin.nodes['tracking-driver-list'])), 'empty admin map has no simulated cars');
  const ride = { id: 1, driver: '<img src=x>', plate: 'TEST', vehicle: 'Synthetic car', origin: 'Start', destination: 'UTeM', location: { lat: 2.31, lng: 102.31, accuracy: 10, heading: 0, capturedAt: 1000, ageSeconds: 0 } };
  await admin.poll({ rides: [ride] });
  check(admin.allMarkers.length === 1 && /Live GPS/.test(text(admin.nodes['tracking-driver-list'])), 'a received GPS fix creates a live car with driver details');
  check(admin.nodes['tracking-driver-list'].children[0].children[1].textContent === '<img src=x>', 'driver-supplied text is rendered as text rather than HTML');
  const moved = { ...ride, location: { ...ride.location, lat: 2.32, lng: 102.32, capturedAt: 1005 } };
  await admin.poll({ rides: [moved] });
  admin.advance(1750); admin.frame();
  check(Math.abs(admin.allMarkers[0].point[0] - 2.315) < 1e-8, 'the car animates through an intermediate map position');
  admin.advance(1750); admin.frame();
  check(Math.abs(admin.allMarkers[0].point[0] - 2.32) < 1e-8, 'car animation ends at the received GPS coordinate');
  await admin.poll({ rides: [moved] });
  check(admin.frames.size === 0, 'repeated polls of the same GPS fix do not invent movement');
  admin.advance(31000); admin.tick();
  check(/Last known location/.test(text(admin.nodes['tracking-driver-list'])) && /^0 live/.test(admin.nodes['live-driver-count'].textContent), 'a silent GPS feed becomes stale without waiting for another server response');
  await admin.poll(new Error('offline'));
  check(/Connection interrupted/.test(admin.nodes['tracking-feed-status'].textContent) && !/^1 live/.test(admin.nodes['live-driver-count'].textContent), 'network failures cannot leave cars labelled live');
  await admin.poll({ rides: [] });
  check(admin.allMarkers[0].removed, 'completed or removed trips disappear from the map');
  await admin.poll({ rides: [ride] }); await admin.poll({ status: 401 });
  check(admin.allMarkers.at(-1).removed && /session ended/i.test(text(admin.nodes['tracking-driver-list'])), 'session expiry clears visible GPS locations and stops polling');
  const noMap = adminHarness({ leaflet: false }); await settle(); await noMap.poll({ rides: [ride] });
  check(/could not load/.test(noMap.nodes['admin-driver-map'].textContent) && /Live GPS/.test(text(noMap.nodes['tracking-driver-list'])), 'driver coordinates and status still work if the map library fails');
  const reduced = adminHarness({ reducedMotion: true }); await settle(); await reduced.poll({ rides: [ride] }); await reduced.poll({ rides: [moved] }); reduced.frame();
  check(reduced.allMarkers[0].point[0] === 2.32, 'reduced-motion preference disables map interpolation');
  const planned = adminHarness(); await settle();
  await planned.poll({rides:[{...ride,location:null,plannedStart:{lat:2.30,lng:102.30},gpsMessage:'Browser location permission is blocked.'}]});
  check(planned.allMarkers.length === 0 && /Waiting for device GPS/.test(text(planned.nodes['tracking-driver-list'])) && /^0 live/.test(planned.nodes['live-driver-count'].textContent), 'a waiting trip never substitutes the planned start for real GPS coordinates');
  check(/permission is blocked/.test(text(planned.nodes['tracking-driver-list'])), 'admin displays the driver GPS failure reason');
  await planned.poll({rides:[ride]});
  check(planned.allMarkers[0].point[0]===2.31 && planned.frames.size===0, 'the first real GPS fix places the pixel car directly at the received latitude and longitude');
  console.log(`${checks} live GPS, animation and failure-state checks passed; no real GPS or network used.`);
})().catch((error) => { console.error(error); process.exit(1); });
