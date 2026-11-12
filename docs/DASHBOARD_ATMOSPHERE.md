# Shared RideUTeM interface

All routed pages load the original forest, cream, sage and orange palette through
theme-head.blade.php. workspace-experience.css adds consistent sidebar navigation,
cards, controls and responsive menus for passenger, driver and admin workspaces.
The old dark dashboard-atmosphere.css is retired and no longer loaded.

interface-motion.js uses one IntersectionObserver for finite, staggered card
reveals and event-driven button ripples. No library, polling or continuous frame
loop is added. Nested cards animate with their parent. Live map containers are
excluded from reveals. Route dashes make two slow passes; progress indicators grow
once with their card. Reduced-motion settings disable these effects.

The welcome page retains the existing two-shot car cinematic. Login and signup
retain the UTeM aerial background and green authentication card with a coordinated
entrance. Their form submission and navigation lifecycles are preserved.

php tests/render_theme_previews.php checks shared style inclusion across 28
anonymous previews and rejects the retired dark stylesheet. Forms are inert and
no database records are created. Only safe device, menu and interface scripts run.
php tests/passenger_overview.php checks eight booking summary scenarios.