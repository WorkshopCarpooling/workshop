<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$adminUser = requireAdmin();
$logoutToken = appLogoutToken();
$pdo = databaseConnection();
$_SESSION['admin_csrf'] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION['admin_csrf'];
$memberNotice = '';
$memberNoticeType = '';
$vehicleNotice = '';
$vehicleNoticeType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['approve_member', 'approve_vehicle', 'reject_vehicle'], true)) {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $action = (string) ($_POST['action'] ?? '');
    $memberId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $vehicleId = filter_var($_POST['vehicle_id'] ?? null, FILTER_VALIDATE_INT);
    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $memberNotice = 'Your session expired. Refresh and try again.';
        $memberNoticeType = 'error';
    } elseif ($action === 'approve_member' && !$memberId) {
        $memberNotice = 'That account could not be identified.';
        $memberNoticeType = 'error';
    } elseif ($action === 'approve_member') {
        $approve = $pdo->prepare("UPDATE users SET account_status = 'active' WHERE user_id = :user_id AND account_status = 'pending' AND role IN ('student', 'staff')");
        $approve->execute(['user_id' => $memberId]);
        $memberNotice = $approve->rowCount() === 1 ? 'Account approved. The member can now log in.' : 'This account is no longer pending.';
        $memberNoticeType = $approve->rowCount() === 1 ? 'success' : 'error';
    } elseif (!$vehicleId) {
        $memberNotice = 'That vehicle could not be identified.';
        $memberNoticeType = 'error';
    } else {
        $nextStatus = $action === 'approve_vehicle' ? 'approved' : 'rejected';
        $adminNote = trim((string) ($_POST['admin_note'] ?? ''));
        $reviewVehicle = $pdo->prepare('UPDATE vehicles SET verification_status = :status, admin_note = :admin_note, reviewed_by = :admin_id, reviewed_at = CURRENT_TIMESTAMP WHERE vehicle_id = :vehicle_id AND verification_status = \'pending\'');
        $reviewVehicle->execute([
            'status' => $nextStatus,
            'admin_note' => $adminNote !== '' ? mb_substr($adminNote, 0, 255) : null,
            'admin_id' => $adminUser['user_id'],
            'vehicle_id' => $vehicleId,
        ]);
        $memberNotice = $reviewVehicle->rowCount() === 1
            ? ($nextStatus === 'approved' ? 'Vehicle approved. The driver can now publish rides with it.' : 'Vehicle declined. The driver can update the details and submit again.')
            : 'This vehicle is no longer waiting for review.';
        $memberNoticeType = $reviewVehicle->rowCount() === 1 ? 'success' : 'error';
    }
}

if (in_array(($_POST['action'] ?? ''), ['approve_vehicle', 'reject_vehicle'], true)) {
    $vehicleNotice = $memberNotice;
    $vehicleNoticeType = $memberNoticeType;
    $memberNotice = '';
    $memberNoticeType = '';
}

$pendingQuery = $pdo->query("SELECT user_id, full_name, email, role, account_type, created_at FROM users WHERE account_status = 'pending' AND role IN ('student', 'staff') ORDER BY created_at ASC");
$pendingMembers = $pendingQuery->fetchAll();
$pendingVehiclesQuery = $pdo->query("SELECT v.vehicle_id, v.vehicle_type, v.vehicle_color, v.vehicle_plate, v.created_at, u.full_name AS driver_name, u.email AS driver_email FROM vehicles v JOIN users u ON u.user_id = v.user_id WHERE v.verification_status = 'pending' ORDER BY v.created_at ASC");
$pendingVehicles = $pendingVehiclesQuery->fetchAll();
$memberCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('student', 'staff')")->fetchColumn();

require dirname(__DIR__) . '/views/admin.php';
