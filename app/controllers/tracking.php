<?php
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$currentUser = requireCommuter();
$logoutToken = appLogoutToken();

require dirname(__DIR__) . '/views/tracking.php';
