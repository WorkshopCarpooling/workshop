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
$campusRoutesQuery = $pdo->query('SELECT route_id, route_name, direction, origin_name, destination_name FROM campus_routes WHERE is_active = 1 ORDER BY route_id');
$campusRoutes = $campusRoutesQuery->fetchAll();
$routeStopsByRoute = [];
$pickupStopsQuery = $pdo->query('SELECT s.stop_id, s.route_id, s.stop_order, s.stop_name FROM campus_route_stops s JOIN campus_routes r ON r.route_id = s.route_id WHERE r.is_active = 1 ORDER BY s.route_id, s.stop_order');
foreach ($pickupStopsQuery->fetchAll() as $pickupStop) {
    $routeStopsByRoute[(int) $pickupStop['route_id']][] = $pickupStop;
}
foreach ($campusRoutes as &$campusRoute) {
    $campusRoute['stops'] = $routeStopsByRoute[(int) $campusRoute['route_id']] ?? [];
}
unset($campusRoute);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_seat') {
    $token = $_POST['csrf_token'] ?? '';
    $rideId = filter_var($_POST['ride_id'] ?? null, FILTER_VALIDATE_INT);
    $seats = filter_var($_POST['seats_requested'] ?? 1, FILTER_VALIDATE_INT);
    $pickupStopId = filter_var($_POST['pickup_stop_id'] ?? null, FILTER_VALIDATE_INT);
    $message = 'Could not send your seat request. Please check the ride and try again.';
    if (is_string($token) && hash_equals($bookingCsrf, $token) && ($currentUser['account_type'] ?? 'passenger') === 'passenger' && in_array(($currentUser['role'] ?? ''), ['student', 'staff'], true) && $rideId && $pickupStopId && $seats && $seats >= 1 && $seats <= 3) {
        try {
            $pdo->beginTransaction();
            $rideStatement = $pdo->prepare("SELECT r.ride_id, r.route_id, r.driver_id, r.available_seats FROM rides r JOIN users u ON u.user_id = r.driver_id WHERE r.ride_id = :ride_id AND r.route_id IS NOT NULL AND r.ride_status = 'scheduled' AND r.departure_at > NOW() AND u.account_status = 'active' FOR UPDATE");
            $rideStatement->execute(['ride_id' => $rideId]);
            $ride = $rideStatement->fetch();
            $pickupStatement = $pdo->prepare('SELECT s.stop_name FROM ride_route_stops rrs JOIN campus_route_stops s ON s.stop_id = rrs.stop_id WHERE rrs.ride_id = :ride_id AND rrs.stop_id = :stop_id LIMIT 1');
            $pickupStatement->execute(['ride_id' => $rideId, 'stop_id' => $pickupStopId]);
            $pickupStop = $pickupStatement->fetch();
            if ($ride && $pickupStop && (int) $ride['driver_id'] !== (int) $currentUser['user_id'] && (int) $ride['available_seats'] >= $seats) {
                $existingStatement = $pdo->prepare('SELECT booking_id, booking_status FROM ride_bookings WHERE ride_id = :ride_id AND passenger_id = :passenger_id FOR UPDATE');
                $existingStatement->execute(['ride_id' => $rideId, 'passenger_id' => $currentUser['user_id']]);
                $existing = $existingStatement->fetch();
                if ($existing && in_array($existing['booking_status'], ['pending', 'accepted'], true)) {
                    $message = $existing['booking_status'] === 'pending' ? 'Your request is already waiting for the driver.' : 'You already have a confirmed seat on this ride.';
                } elseif ($existing) {
                    $update = $pdo->prepare("UPDATE ride_bookings SET seats_requested = :seats, pickup_stop_id = :pickup_stop_id, pickup_label = :pickup_label, pickup_latitude = NULL, pickup_longitude = NULL, booking_status = 'pending' WHERE booking_id = :booking_id");
                    $update->execute(['seats' => $seats, 'pickup_stop_id' => $pickupStopId, 'pickup_label' => $pickupStop['stop_name'], 'booking_id' => $existing['booking_id']]);
                    $message = 'Seat request sent. The driver will review it.';
                } else {
                    $insert = $pdo->prepare("INSERT INTO ride_bookings (ride_id, passenger_id, pickup_stop_id, seats_requested, pickup_label, pickup_latitude, pickup_longitude, booking_status) VALUES (:ride_id, :passenger_id, :pickup_stop_id, :seats, :pickup_label, NULL, NULL, 'pending')");
                    $insert->execute(['ride_id' => $rideId, 'passenger_id' => $currentUser['user_id'], 'pickup_stop_id' => $pickupStopId, 'seats' => $seats, 'pickup_label' => $pickupStop['stop_name']]);
                    $message = 'Seat request sent. The driver will review it.';
                }
            } elseif ($ride && !$pickupStop) {
                $message = 'Choose one of the fixed pickup points offered for this ride.';
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Ride booking request failed: ' . $error->getMessage());
        }
    } else {
        $message = is_string($token) && hash_equals($bookingCsrf, $token) && $rideId && !$pickupStopId
            ? 'Choose one of the fixed pickup points before requesting a seat.'
            : 'Your session expired or your account cannot request this ride.';
    }
    $_SESSION['booking_notice'] = $message;
    header('Location: findride.php');
    exit;
}
$ridesQuery = $pdo->prepare("SELECT r.ride_id, r.route_id, cr.route_name, cr.direction, r.pickup_point, r.destination, r.departure_at, r.available_seats, r.contribution_per_seat, r.vehicle_type, r.vehicle_color, r.notes, r.ride_status, u.full_name AS driver_name, rb.booking_status AS my_booking_status FROM rides r JOIN campus_routes cr ON cr.route_id = r.route_id AND cr.is_active = 1 INNER JOIN users u ON u.user_id = r.driver_id LEFT JOIN ride_bookings rb ON rb.ride_id = r.ride_id AND rb.passenger_id = :passenger_id WHERE r.driver_id <> :viewer_id AND u.role IN ('student', 'staff') AND u.account_type = 'driver' AND u.account_status = 'active' AND r.ride_status = 'scheduled' AND r.departure_at >= NOW() AND r.available_seats > 0 ORDER BY r.departure_at ASC LIMIT 100");
$ridesQuery->execute(['passenger_id' => $currentUser['user_id'], 'viewer_id' => $currentUser['user_id']]);
$rideRows = $ridesQuery->fetchAll();
$stopsByRide = [];
if ($rideRows) {
    $rideIds = array_map(static fn (array $ride): int => (int) $ride['ride_id'], $rideRows);
    $placeholders = implode(',', array_fill(0, count($rideIds), '?'));
    $rideStopsQuery = $pdo->prepare("SELECT rrs.ride_id, s.stop_id, s.stop_name, s.stop_order FROM ride_route_stops rrs JOIN campus_route_stops s ON s.stop_id = rrs.stop_id WHERE rrs.ride_id IN ($placeholders) ORDER BY s.stop_order");
    $rideStopsQuery->execute($rideIds);
    foreach ($rideStopsQuery->fetchAll() as $rideStop) {
        $stopsByRide[(int) $rideStop['ride_id']][] = ['id' => (int) $rideStop['stop_id'], 'name' => $rideStop['stop_name']];
    }
}
$availableRides = [];
foreach ($rideRows as $ride) {
    $initials = '';
    foreach (preg_split('/\s+/', trim($ride['driver_name'])) as $part) {
        if ($part !== '') {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
    }
    $availableRides[] = [
        'id' => (int) $ride['ride_id'],
        'routeId' => (int) $ride['route_id'],
        'routeName' => $ride['route_name'],
        'direction' => $ride['direction'],
        'pickupStops' => $stopsByRide[(int) $ride['ride_id']] ?? [],
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
