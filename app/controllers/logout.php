<?php
require_once dirname(__DIR__, 2) . '/includes/auth.php';
startAppSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (($_SESSION['user']['role'] ?? '') === 'admin' ? 'admin.php' : 'dashboard.php'));
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['logout_csrf'] ?? '';
if (!is_string($submittedToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    http_response_code(403);
    exit('Invalid logout request. Return to the page and try again.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
