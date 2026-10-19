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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_member') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $memberId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $memberNotice = 'Your session expired. Refresh and try again.';
        $memberNoticeType = 'error';
    } elseif (!$memberId) {
        $memberNotice = 'That account could not be identified.';
        $memberNoticeType = 'error';
    } else {
        $approve = $pdo->prepare("UPDATE users SET account_status = 'active' WHERE user_id = :user_id AND account_status = 'pending' AND role IN ('student', 'staff')");
        $approve->execute(['user_id' => $memberId]);
        $memberNotice = $approve->rowCount() === 1 ? 'Account approved. The member can now log in.' : 'This account is no longer pending.';
        $memberNoticeType = $approve->rowCount() === 1 ? 'success' : 'error';
    }
}

$pendingQuery = $pdo->query("SELECT user_id, full_name, email, role, account_type, created_at FROM users WHERE account_status = 'pending' AND role IN ('student', 'staff') ORDER BY created_at ASC");
$pendingMembers = $pendingQuery->fetchAll();
$memberCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('student', 'staff')")->fetchColumn();

require dirname(__DIR__) . '/views/admin.php';
