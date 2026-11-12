# Shared RideUTeM palette

All routed application views load `partials/theme-head.blade.php` after their page styles. This partial loads the versioned `public/css/system-theme.css` stylesheet. Edit its brand variables to change the system palette together.

- Forest green: #254B39, primary actions and navigation.
- Warm cream: #F4F5EC, page backgrounds and text on dark buttons.
- Burnt orange: #C98148, small brand accents and destination markers.
- Soft sage: #CBD8BD, selected surfaces and supporting controls.
- Deep green: #14251E, account cards and dark surfaces.

Coverage includes welcome/cinematic intro, login, signup, passenger overview/bookings/schedule/history, ride search, driver overview/offers/requests/vehicles/schedule/history/route, admin overview/live/rides/members/vehicles, timetable, research, carbon and tracking. The standalone offline screen uses the same palette. Unused Laravel starter markup is unchanged.

The shared stylesheet changes colors without changing layouts, routes, account permissions or data. Existing error/red and warning semantics remain distinct. The UTeM photo and car assets are unchanged.

Validation: `tests/render_theme_previews.php` renders anonymous static previews for all major role pages without creating account or trip records. It removes scripts and makes forms inert. Browser checks cover actual account pages plus passenger, driver and admin previews. The theme is confirmed as the final stylesheet on live pages; desktop and phone login layouts remain intact.