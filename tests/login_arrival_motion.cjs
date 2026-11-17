// Exercise the actual transition controller without accounts, credentials or a browser.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../public/js/login-arrival.js'), 'utf8');
function boot({ reduced = false, initiallyInert = false, missingTemplate = false } = {}) {
  const events = new Map(), windowEvents = new Map(), timers = new Map();
  const state = { appended: false, removed: false, marker: true, dispatched: 0 };
  const shell = { inert: initiallyInert };
  const classes = new Set();
  const overlayEvents = new Map();
  const skipEvents = new Map();
  const overlay = {
    remove: () => { state.removed = true; },
    querySelector: () => ({ addEventListener: (name, action) => skipEvents.set(name, action) }),
    addEventListener: (name, action) => overlayEvents.set(name, action),
  };
  const marker = { remove: () => { state.marker = false; } };
  const template = { content: { firstElementChild: { cloneNode: () => overlay } }, remove() {} };
  const document = {
    hidden: false,
    querySelector: () => marker,
    querySelectorAll: () => [shell],
    getElementById: () => missingTemplate ? null : template,
    body: { append: () => { state.appended = true; } },
    documentElement: { classList: { add: name => classes.add(name), remove: name => classes.delete(name) } },
    addEventListener: (name, action) => events.set(name, action),
    removeEventListener: name => events.delete(name),
    dispatchEvent: () => { state.dispatched++; },
  };
  vm.runInNewContext(source, {
    document, matchMedia: () => ({ matches: reduced, addEventListener() {} }),
    window: { addEventListener: (name, action) => windowEvents.set(name, action) },
    setTimeout: action => { timers.set(1, action); return 1; },
    clearTimeout: id => timers.delete(id),
    CustomEvent: class { constructor(type) { this.type = type; } },
  });
  return { state, shell, classes, overlay, overlayEvents, skipEvents, events, windowEvents, timers };
}
const finished = fixture => {
  assert.equal(fixture.state.removed, true);
  assert.equal(fixture.state.marker, false);
  assert.equal(fixture.shell.inert, false);
  assert.equal(fixture.classes.size, 0);
  assert.equal(fixture.state.dispatched, 1);
  assert.equal(fixture.timers.size, 0);
};
let test = boot();
assert.equal(test.state.appended, true);
assert.equal(test.shell.inert, true);
test.overlayEvents.get('animationend')({ target: {}, animationName: 'arrival-pin' });
assert.equal(test.state.removed, false, 'Child animation must not finish the transition.');
const dismiss = test.overlayEvents.get('animationend');
dismiss({ target: test.overlay, animationName: 'arrival-dismiss' });
finished(test);
dismiss({ target: test.overlay, animationName: 'arrival-dismiss' });
assert.equal(test.state.dispatched, 1, 'Completion must only fire once.');
test = boot(); test.skipEvents.get('click')(); finished(test);
test = boot(); test.events.get('keydown')({ key: 'Escape' }); finished(test);
test = boot(); test.timers.get(1)(); finished(test);
test = boot(); test.windowEvents.get('pageshow')({ persisted: true }); finished(test);
test = boot({ initiallyInert: true }); test.skipEvents.get('click')();
assert.equal(test.shell.inert, true, 'Restore the original inert state.');
test = boot({ reduced: true });
assert.equal(test.state.appended, false);
assert.equal(test.state.marker, false);
assert.equal(test.state.dispatched, 1);
test = boot({ missingTemplate: true });
assert.equal(test.state.appended, false);
assert.equal(test.state.marker, false);
assert.equal(test.state.dispatched, 1);
console.log('Login arrival: 8 lifecycle checks passed.');
