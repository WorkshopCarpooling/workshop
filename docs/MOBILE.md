# Responsive website and future mobile app

The current system is a responsive website. Install buttons, installation instructions, app prompts and active PWA registration have been removed. Existing RideUTeM service-worker registrations are retired when the updated website loads. Phone/tablet navigation, touch controls, location selection, connection status and duplicate form-submission protection remain available.

## Future idea: Flutter or native Android

A mobile application is a possible later development phase. Reuse Laravel and MySQL on a hosted server, add authenticated APIs, and build a mobile interface for login, ride search, bookings, driver actions and maps.

- Flutter can provide Android and iPhone interfaces from one codebase.
- Kotlin can provide a native Android application.
- Android Studio is a development environment that can be used for either Flutter or native Android work.

Authentication, maps/location permissions, booking synchronization, mobile layouts and platform builds would need implementation and testing. The PHP server and database remain hosted rather than being copied into an APK/IPA. No native app or app-store submission is part of the current website work.

## Website testing on a phone

Use the computer's reachable local-network address to test the website from another device. `localhost` on the phone refers to the phone itself. HTTPS hosting is needed for features such as device location in normal deployment.

No database records are inserted by the responsive website changes or the installation-feature removal.
