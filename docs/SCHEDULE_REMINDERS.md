# Passenger schedule robot

The robot is visible at the bottom right of authenticated passenger pages.
It automatically opens a reminder 30 minutes and 10 minutes before a weekly
timetable session or a confirmed ride's driver departure. An accepted booking is
required for ride reminders; pending, cancelled and completed rides are excluded.
The ride departure is not represented as an exact passenger pickup time.

Click the robot to inspect the next event. Snooze hides an occurrence for five
minutes; Dismiss silences that occurrence for this passenger in this browser.
The next weekly occurrence has a different timestamp and can remind again.
Closing the card permits the later ten-minute reminder. The card does not take
keyboard focus when it opens automatically. Escape returns focus to the robot.

These are in-website reminders: the site must remain open. Background timers may
be delayed by the browser; returning to the page checks current events. This does
not install push notifications or claim delivery while the browser is closed.

The authenticated passenger-only endpoint returns upcoming events for the next
24 hours and uses the configured Asia/Kuala_Lumpur timezone. It reads existing
data without adding or changing timetable entries or bookings. Event labels use
textContent, links must stay on the current origin, and responses are private
and uncached. Schedule polling runs once per minute while visible; countdowns
update every 30 seconds. Browser dismissal storage is scoped by passenger ID.

Tests: `php tests/schedule_reminders.php` uses SQLite memory only, including user
isolation, role access, accepted/cancelled bookings, weekly rollover, midnight,
Sunday and deleted sessions. `node tests/schedule_robot_browser.cjs` checks clock
windows, snooze, dismissal persistence, expiry, network refresh, keyboard access
and blocked storage without using real schedules or network requests.

The static `public/device-review/schedule-robot-preview.html` contains an explicitly
labelled sample event for visual verification. Its storage is separate from real
reminder state. It does not change users' real schedules.

## Robot artwork

Original transparent PNG: `public/images/schedule-robot.png`.
Generated using the built-in image_gen tool, inspired by the general appearance
of the user's reference: https://www.pinterest.com/pin/1042020432554046235/ .
The reference image is not downloaded or embedded in the application.

Exact generation prompt:

> Create one original friendly 3D robot mascot as a transparent PNG cutout for a small bottom-right reminder widget in a university carpool website. Style reference described: a cute white rounded robot, glossy black face display, luminous cyan-blue circular eyes and a small kind smile, polished metallic ear joints, a compact egg-shaped floating body and short articulated arms. Make an original design inspired by those general qualities, not an exact reproduction. Pearl-white shell with very subtle mint reflections to suit a green campus website; one small cyan status light on the chest. Full body including both hands fully visible, front-facing three-quarter slight turn toward viewer, one hand raised in a gentle greeting. Large expressive head and simple silhouette, readable at 100 pixels tall. High-quality soft 3D render, smooth rounded toy-like forms. Isolated subject on genuinely transparent background, no backdrop, no ground plane, no text, no logos, no watermark, no border, no UI or speech bubble. Tight square framing with comfortable transparent padding, robot occupies most of the image.
