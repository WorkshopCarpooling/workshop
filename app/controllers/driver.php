<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$currentUser = requireCommuter();
if (!in_array(($currentUser['role'] ?? ''), ['student', 'staff'], true) || ($currentUser['account_type'] ?? 'passenger') !== 'driver') {
    header('Location: dashboard.php');
    exit;
}
$logoutToken = appLogoutToken();
startAppSession();
$_SESSION['driver_csrf'] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION['driver_csrf'];
$pdo = databaseConnection();
$notice = '';
$noticeType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $notice = 'Your session expired. Refresh and try again.';
        $noticeType = 'error';
    } elseif (($_POST['action'] ?? '') === 'submit_vehicle') {
        $vehicleType = trim((string) ($_POST['vehicle_type'] ?? ''));
        $vehicleColor = trim((string) ($_POST['vehicle_color'] ?? ''));
        $vehiclePlate = strtoupper(trim((string) ($_POST['vehicle_plate'] ?? '')));
        if ($vehicleType === '' || mb_strlen($vehicleType) > 80 || $vehicleColor === '' || mb_strlen($vehicleColor) > 60 || $vehiclePlate === '' || mb_strlen($vehiclePlate) > 20) {
            $notice = 'Enter a valid car model, colour, and number plate.';
            $noticeType = 'error';
        } else {
            $existingVehicleQuery = $pdo->prepare('SELECT vehicle_id, verification_status FROM vehicles WHERE user_id = :user_id AND vehicle_plate = :vehicle_plate LIMIT 1');
            $existingVehicleQuery->execute(['user_id' => $currentUser['user_id'], 'vehicle_plate' => $vehiclePlate]);
            $existingVehicle = $existingVehicleQuery->fetch();
            if ($existingVehicle && $existingVehicle['verification_status'] !== 'rejected') {
                $notice = $existingVehicle['verification_status'] === 'approved'
                    ? 'This vehicle is already approved.'
                    : 'This vehicle is already waiting for admin review.';
                $noticeType = 'error';
            } elseif ($existingVehicle) {
                $resubmitVehicle = $pdo->prepare("UPDATE vehicles SET vehicle_type = :vehicle_type, vehicle_color = :vehicle_color, verification_status = 'pending', admin_note = NULL, reviewed_by = NULL, reviewed_at = NULL WHERE vehicle_id = :vehicle_id AND user_id = :user_id AND verification_status = 'rejected'");
                $resubmitVehicle->execute(['vehicle_type' => $vehicleType, 'vehicle_color' => $vehicleColor, 'vehicle_id' => $existingVehicle['vehicle_id'], 'user_id' => $currentUser['user_id']]);
                $notice = 'Vehicle details updated and sent for admin review.';
                $noticeType = 'success';
            } else {
                $insertVehicle = $pdo->prepare("INSERT INTO vehicles (user_id, vehicle_type, vehicle_color, vehicle_plate, verification_status) VALUES (:user_id, :vehicle_type, :vehicle_color, :vehicle_plate, 'pending')");
                $insertVehicle->execute(['user_id' => $currentUser['user_id'], 'vehicle_type' => $vehicleType, 'vehicle_color' => $vehicleColor, 'vehicle_plate' => $vehiclePlate]);
                $notice = 'Vehicle details sent. An administrator must approve them before you can publish a ride.';
                $noticeType = 'success';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'create_ride') {
        $pickup = trim((string) ($_POST['pickup_point'] ?? ''));
        $destination = trim((string) ($_POST['destination'] ?? ''));
        $departure = trim((string) ($_POST['departure_at'] ?? ''));
        $seats = filter_var($_POST['available_seats'] ?? null, FILTER_VALIDATE_INT);
        $contribution = filter_var($_POST['contribution_per_seat'] ?? null, FILTER_VALIDATE_FLOAT);
        $vehicleId = filter_var($_POST['vehicle_id'] ?? null, FILTER_VALIDATE_INT);
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $departureTimestamp = strtotime($departure);
        $vehicleQuery = $pdo->prepare("SELECT vehicle_id, vehicle_type, vehicle_color, vehicle_plate FROM vehicles WHERE vehicle_id = :vehicle_id AND user_id = :user_id AND verification_status = 'approved' LIMIT 1");
        $vehicleQuery->execute(['vehicle_id' => $vehicleId ?: 0, 'user_id' => $currentUser['user_id']]);
        $vehicle = $vehicleQuery->fetch();

        if ($pickup === '' || mb_strlen($pickup) > 180 || $destination === '' || mb_strlen($destination) > 180) {
            $notice = 'Enter a pickup point and destination (up to 180 characters each).';
            $noticeType = 'error';
        } elseif (!$departureTimestamp || $departureTimestamp <= time()) {
            $notice = 'Choose a departure date and time in the future.';
            $noticeType = 'error';
        } elseif (!$seats || $seats < 1 || $seats > 6) {
            $notice = 'Choose between 1 and 6 available seats.';
            $noticeType = 'error';
        } elseif ($contribution === false || $contribution < 0 || $contribution > 9999.99) {
            $notice = 'Enter a valid contribution per seat.';
            $noticeType = 'error';
        } elseif (!$vehicle || mb_strlen($notes) > 500) {
            $notice = !$vehicle ? 'Choose one of your admin-approved vehicles before publishing a ride.' : 'Keep trip notes under 500 characters.';
            $noticeType = 'error';
        } else {
            $insert = $pdo->prepare('INSERT INTO rides (driver_id, vehicle_id, pickup_point, destination, departure_at, available_seats, contribution_per_seat, vehicle_description, vehicle_type, vehicle_color, vehicle_plate, notes) VALUES (:driver_id, :vehicle_id, :pickup_point, :destination, :departure_at, :available_seats, :contribution, :vehicle, :vehicle_type, :vehicle_color, :vehicle_plate, :notes)');
            $insert->execute([
                'driver_id' => $currentUser['user_id'],
                'vehicle_id' => $vehicle['vehicle_id'],
                'pickup_point' => $pickup,
                'destination' => $destination,
                'departure_at' => date('Y-m-d H:i:s', $departureTimestamp),
                'available_seats' => $seats,
                'contribution' => number_format((float) $contribution, 2, '.', ''),
                'vehicle' => $vehicle['vehicle_type'],
                'vehicle_type' => $vehicle['vehicle_type'],
                'vehicle_color' => $vehicle['vehicle_color'],
                'vehicle_plate' => $vehicle['vehicle_plate'],
                'notes' => $notes !== '' ? $notes : null,
            ]);
            $notice = 'Your ride is published and visible in My driving.';
            $noticeType = 'success';
        }
    } elseif (in_array(($_POST['action'] ?? ''), ['accept_booking', 'decline_booking'], true)) {
        $bookingId = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$bookingId) {
            $notice = 'That seat request could not be found.';
            $noticeType = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                $requestQuery = $pdo->prepare("SELECT b.booking_id, b.booking_status, b.seats_requested, r.ride_id, r.available_seats FROM ride_bookings b JOIN rides r ON r.ride_id = b.ride_id WHERE b.booking_id = :booking_id AND r.driver_id = :driver_id AND r.ride_status = 'scheduled' FOR UPDATE");
                $requestQuery->execute(['booking_id' => $bookingId, 'driver_id' => $currentUser['user_id']]);
                $request = $requestQuery->fetch();
                if (!$request || $request['booking_status'] !== 'pending') {
                    $notice = 'This request has already been handled or the ride is no longer available.';
                    $noticeType = 'error';
                } elseif (($_POST['action'] ?? '') === 'accept_booking') {
                    if ((int) $request['available_seats'] < (int) $request['seats_requested']) {
                        $notice = 'There are not enough seats left to accept this request.';
                        $noticeType = 'error';
                    } else {
                        $seatUpdate = $pdo->prepare('UPDATE rides SET available_seats = available_seats - :seats WHERE ride_id = :ride_id AND available_seats >= :minimum');
                        $seatUpdate->execute(['seats' => $request['seats_requested'], 'ride_id' => $request['ride_id'], 'minimum' => $request['seats_requested']]);
                        if ($seatUpdate->rowCount() === 1) {
                            $bookingUpdate = $pdo->prepare("UPDATE ride_bookings SET booking_status = 'accepted' WHERE booking_id = :booking_id AND booking_status = 'pending'");
                            $bookingUpdate->execute(['booking_id' => $bookingId]);
                            $notice = 'Seat request accepted. The available seat count has been updated.';
                            $noticeType = 'success';
                        } else {
                            $notice = 'There are not enough seats left to accept this request.';
                            $noticeType = 'error';
                        }
                    }
                } else {
                    $bookingUpdate = $pdo->prepare("UPDATE ride_bookings SET booking_status = 'declined' WHERE booking_id = :booking_id AND booking_status = 'pending'");
                    $bookingUpdate->execute(['booking_id' => $bookingId]);
                    $notice = 'Seat request declined.';
                    $noticeType = 'success';
                }
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Ride booking decision failed: ' . $error->getMessage());
                $notice = 'Could not update the seat request. Please try again.';
                $noticeType = 'error';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'update_status') {
        $rideId = filter_var($_POST['ride_id'] ?? null, FILTER_VALIDATE_INT);
        $nextStatus = (string) ($_POST['next_status'] ?? '');
        $allowedTransitions = [
            'in_progress' => ['scheduled'],
            'completed' => ['in_progress'],
            'cancelled' => ['scheduled'],
        ];
        if (!$rideId || !isset($allowedTransitions[$nextStatus])) {
            $notice = 'That ride action is not valid.';
            $noticeType = 'error';
        } else {
            $update = $pdo->prepare('UPDATE rides SET ride_status = :next_status WHERE ride_id = :ride_id AND driver_id = :driver_id AND ride_status = :current_status');
            $update->execute([
                'next_status' => $nextStatus,
                'ride_id' => $rideId,
                'driver_id' => $currentUser['user_id'],
                'current_status' => $allowedTransitions[$nextStatus][0],
            ]);
            $notice = $update->rowCount() === 1 ? 'Ride status updated.' : 'The ride status has changed or the ride was not found.';
            $noticeType = $update->rowCount() === 1 ? 'success' : 'error';
        }
    }
}

$ridesStatement = $pdo->prepare('SELECT ride_id, pickup_point, destination, departure_at, available_seats, contribution_per_seat, vehicle_description, vehicle_type, vehicle_color, vehicle_plate, notes, ride_status FROM rides WHERE driver_id = :driver_id ORDER BY departure_at DESC');
$ridesStatement->execute(['driver_id' => $currentUser['user_id']]);
$rides = $ridesStatement->fetchAll();
$vehiclesStatement = $pdo->prepare('SELECT vehicle_id, vehicle_type, vehicle_color, vehicle_plate, verification_status, admin_note FROM vehicles WHERE user_id = :user_id ORDER BY created_at DESC');
$vehiclesStatement->execute(['user_id' => $currentUser['user_id']]);
$vehicles = $vehiclesStatement->fetchAll();
$approvedVehicles = array_values(array_filter($vehicles, static fn (array $vehicle): bool => $vehicle['verification_status'] === 'approved'));
$bookingRequestsStatement = $pdo->prepare("SELECT b.booking_id, b.ride_id, b.seats_requested, b.booking_status, b.created_at, b.pickup_label, b.pickup_latitude, b.pickup_longitude, u.full_name AS passenger_name, r.pickup_point, r.destination, r.departure_at, r.ride_status FROM ride_bookings b JOIN rides r ON r.ride_id = b.ride_id JOIN users u ON u.user_id = b.passenger_id WHERE r.driver_id = :driver_id ORDER BY b.created_at DESC");
$bookingRequestsStatement->execute(['driver_id' => $currentUser['user_id']]);
$bookingRequests = $bookingRequestsStatement->fetchAll();
$pendingRequestCount = 0;
foreach ($bookingRequests as $bookingRequest) {
    if ($bookingRequest['booking_status'] === 'pending') $pendingRequestCount++;
}

require dirname(__DIR__) . '/views/driver.php';
