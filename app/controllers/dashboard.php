<?php
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$currentUser = requireCommuter();
if (in_array(($currentUser['role'] ?? ''), ['student', 'staff'], true) && ($currentUser['account_type'] ?? 'passenger') === 'driver') {
    header('Location: driver.php');
    exit;
}
$logoutToken = appLogoutToken();
$pdo = databaseConnection();
$bookingsStatement = $pdo->prepare("SELECT b.booking_status, b.seats_requested, COALESCE(b.pickup_label, r.pickup_point) AS pickup_point, r.destination, r.departure_at, r.contribution_per_seat, u.full_name AS driver_name FROM ride_bookings b JOIN rides r ON r.ride_id = b.ride_id JOIN users u ON u.user_id = r.driver_id WHERE b.passenger_id = :passenger_id ORDER BY r.departure_at DESC LIMIT 20");
$bookingsStatement->execute(['passenger_id' => $currentUser['user_id']]);
$myBookings = $bookingsStatement->fetchAll();

require dirname(__DIR__) . '/views/dashboard.php';
