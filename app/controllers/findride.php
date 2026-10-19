<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$currentUser = requireCommuter();
if (in_array(($currentUser['role'] ?? ''), ['student', 'staff'], true) && ($currentUser['account_type'] ?? 'passenger') === 'driver') {
    header('Location: driver.php');
    exit;
}
$logoutToken = appLogoutToken();
$pdo = databaseConnection();
startAppSession();
$_SESSION['booking_csrf'] ??= bin2hex(random_bytes(32));
$bookingCsrf = $_SESSION['booking_csrf'];
$bookingNotice = $_SESSION['booking_notice'] ?? '';
unset($_SESSION['booking_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_seat') {
    $token = $_POST['csrf_token'] ?? '';
    $rideId = filter_var($_POST['ride_id'] ?? null, FILTER_VALIDATE_INT);
    $seats = filter_var($_POST['seats_requested'] ?? 1, FILTER_VALIDATE_INT);
    $pickupLabel = trim((string) ($_POST['pickup_label'] ?? ''));
    $pickupLatitude = filter_var($_POST['pickup_latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $pickupLongitude = filter_var($_POST['pickup_longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $hasPickupCoordinates = $pickupLatitude !== false && $pickupLatitude !== null && $pickupLongitude !== false && $pickupLongitude !== null
        && $pickupLatitude >= -90 && $pickupLatitude <= 90 && $pickupLongitude >= -180 && $pickupLongitude <= 180;
    if (!$hasPickupCoordinates) {
        $pickupLatitude = $pickupLongitude = null;
        $pickupLabel = '';
    }
    $pickupLabel = mb_substr($pickupLabel, 0, 180);
    $message = 'Could not send your seat request. Please check the ride and try again.';
    if (is_string($token) && hash_equals($bookingCsrf, $token) && ($currentUser['account_type'] ?? 'passenger') === 'passenger' && in_array(($currentUser['role'] ?? ''), ['student', 'staff'], true) && $rideId && $seats && $seats >= 1 && $seats <= 3) {
        try {
            $pdo->beginTransaction();
            $rideStatement = $pdo->prepare("SELECT r.ride_id, r.driver_id, r.available_seats FROM rides r JOIN users u ON u.user_id = r.driver_id WHERE r.ride_id = :ride_id AND r.ride_status = 'scheduled' AND r.departure_at > NOW() AND u.account_status = 'active' FOR UPDATE");
            $rideStatement->execute(['ride_id' => $rideId]);
            $ride = $rideStatement->fetch();
            if ($ride && (int) $ride['driver_id'] !== (int) $currentUser['user_id'] && (int) $ride['available_seats'] >= $seats) {
                $existingStatement = $pdo->prepare('SELECT booking_id, booking_status FROM ride_bookings WHERE ride_id = :ride_id AND passenger_id = :passenger_id FOR UPDATE');
                $existingStatement->execute(['ride_id' => $rideId, 'passenger_id' => $currentUser['user_id']]);
                $existing = $existingStatement->fetch();
                if ($existing && in_array($existing['booking_status'], ['pending', 'accepted'], true)) {
                    $message = $existing['booking_status'] === 'pending' ? 'Your request is already waiting for the driver.' : 'You already have a confirmed seat on this ride.';
                } elseif ($existing) {
                    $update = $pdo->prepare("UPDATE ride_bookings SET seats_requested = :seats, pickup_label = :pickup_label, pickup_latitude = :pickup_latitude, pickup_longitude = :pickup_longitude, booking_status = 'pending' WHERE booking_id = :booking_id");
                    $update->execute(['seats' => $seats, 'pickup_label' => $pickupLabel ?: null, 'pickup_latitude' => $pickupLatitude, 'pickup_longitude' => $pickupLongitude, 'booking_id' => $existing['booking_id']]);
                    $message = 'Seat request sent. The driver will review it.';
                } else {
                    $insert = $pdo->prepare("INSERT INTO ride_bookings (ride_id, passenger_id, seats_requested, pickup_label, pickup_latitude, pickup_longitude, booking_status) VALUES (:ride_id, :passenger_id, :seats, :pickup_label, :pickup_latitude, :pickup_longitude, 'pending')");
                    $insert->execute(['ride_id' => $rideId, 'passenger_id' => $currentUser['user_id'], 'seats' => $seats, 'pickup_label' => $pickupLabel ?: null, 'pickup_latitude' => $pickupLatitude, 'pickup_longitude' => $pickupLongitude]);
                    $message = 'Seat request sent. The driver will review it.';
                }
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Ride booking request failed: ' . $error->getMessage());
        }
    } else {
        $message = 'Your session expired or your account cannot request this ride.';
    }
    $_SESSION['booking_notice'] = $message;
    header('Location: findride.php');
    exit;
}
$ridesQuery = $pdo->prepare("SELECT r.ride_id, r.pickup_point, r.destination, r.departure_at, r.available_seats, r.contribution_per_seat, r.vehicle_type, r.vehicle_color, r.notes, r.ride_status, u.full_name AS driver_name, rb.booking_status AS my_booking_status FROM rides r INNER JOIN users u ON u.user_id = r.driver_id LEFT JOIN ride_bookings rb ON rb.ride_id = r.ride_id AND rb.passenger_id = :passenger_id WHERE r.driver_id <> :viewer_id AND u.role IN ('student', 'staff') AND u.account_type = 'driver' AND u.account_status = 'active' AND ((r.ride_status = 'scheduled' AND r.departure_at >= NOW() AND r.available_seats > 0) OR r.ride_status = 'in_progress') ORDER BY r.departure_at ASC LIMIT 100");
$ridesQuery->execute(['passenger_id' => $currentUser['user_id'], 'viewer_id' => $currentUser['user_id']]);
$availableRides = [];
foreach ($ridesQuery->fetchAll() as $ride) {
    $initials = '';
    foreach (preg_split('/\s+/', trim($ride['driver_name'])) as $part) {
        if ($part !== '') {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
    }
    $availableRides[] = [
        'id' => (int) $ride['ride_id'],
        'driver' => $ride['driver_name'],
        'initials' => mb_substr($initials, 0, 2),
        'departureAt' => date('Y-m-d\TH:i', strtotime($ride['departure_at'])),
        'departureDate' => date('Y-m-d', strtotime($ride['departure_at'])),
        'time' => date('g:i A', strtotime($ride['departure_at'])),
        'pickup' => $ride['pickup_point'],
        'destination' => $ride['destination'],
        'seats' => (int) $ride['available_seats'],
        'cost' => number_format((float) $ride['contribution_per_seat'], 2, '.', ''),
        'vehicleType' => $ride['vehicle_type'],
        'vehicleColor' => $ride['vehicle_color'],
        'notes' => $ride['notes'] ?? '',
        'status' => $ride['ride_status'],
        'bookingStatus' => $ride['my_booking_status'],
    ];
}

require dirname(__DIR__) . '/views/findride.php';
