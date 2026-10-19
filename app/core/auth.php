<?php

function startAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function currentUser(): ?array
{
    startAppSession();
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function requireCommuter(): array
{
    $user = currentUser();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }
    if (($user['role'] ?? '') === 'admin') {
        header('Location: admin.php');
        exit;
    }
    return $user;
}

function requireAdmin(): array
{
    $user = currentUser();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }
    if (($user['role'] ?? '') !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
    return $user;
}

function appLogoutToken(): string
{
    startAppSession();
    $_SESSION['logout_csrf'] ??= bin2hex(random_bytes(32));
    return $_SESSION['logout_csrf'];
}