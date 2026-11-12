# Login arrival

Successful login flashes `workspaceEntrance`. The shared head renders
`partials.login-arrival` only for an authenticated user, consuming that flag with
`session()->pull()`. Ordinary refreshes, failed login and signup do not activate it.
Passenger, driver and admin dashboards all use the same component.

The local SVG road, pins and small car play for about 2.65 seconds. A gentle camera
zoom leads to a white wash and dashboard reveal. The interface card reveals wait
for the arrival-complete event. No network video, animation library or extra
authentication request is involved.

The animated map fills the viewport edge to edge, using a covering SVG with
overscan for the camera rotation and zoom. Desktop and phone layouts share this
full-screen framing, with no size cap, rounded map boundary or faded map edges.

Skip, Escape, reduced motion, returning from browser cache and a 2.9-second fallback
all clean up the overlay and restore the workspace's original interactive state.
No credentials are stored for this effect, and authentication completes normally
before the animation appears.

Validation:
- `node tests/login_arrival_motion.cjs`: eight lifecycle checks.
- `php tests/render_theme_previews.php`: shared styling plus one-time arrival
  activation for all three roles; ordinary-page and auth-page exclusion.
- Browser review: automatic completion, Skip, mobile framing, desktop framing.

The static `device-review/theme/login-arrival-passenger.html` preview replays on
refresh and uses inert sample data. It does not sign in or create records.
