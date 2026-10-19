<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
startAppSession();

$isSignup = ($page ?? 'login') === 'signup';
$title = $isSignup ? 'Create your account' : 'Welcome back';
$formError = '';
$formSuccess = '';
$_SESSION['auth_csrf'] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION['auth_csrf'];

if (isset($_SESSION['user'])) {
    $signedInUser = $_SESSION['user'];
    $isDriver = in_array(($signedInUser['role'] ?? ''), ['student', 'staff'], true) && ($signedInUser['account_type'] ?? 'passenger') === 'driver';
    $destination = ($signedInUser['role'] ?? '') === 'admin' ? 'admin.php' : ($isDriver ? 'driver.php' : 'dashboard.php');
    header('Location: ' . $destination);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $formError = 'Your session expired. Refresh this page and try again.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        try {
            $pdo = databaseConnection();
            if ($isSignup) {
                $fullName = trim((string) ($_POST['name'] ?? ''));
                $fullNameLength = preg_match_all('/./us', $fullName);
                $role = (string) ($_POST['memberType'] ?? '');
                $accountType = (string) ($_POST['accountType'] ?? '');
                $confirmation = (string) ($_POST['confirmPassword'] ?? '');

                if ($fullName === '' || $fullNameLength === false || $fullNameLength > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $formError = 'Enter your name and a valid email address.';
                } elseif (!in_array($role, ['student', 'staff'], true)) {
                    $formError = 'Choose Student or UTeM staff. Admin accounts are assigned separately.';
                } elseif (!in_array($accountType, ['passenger', 'driver'], true)) {
                    $formError = 'Choose whether you are joining as a passenger or driver.';
                } elseif (strlen($password) < 8) {
                    $formError = 'Choose a password with at least 8 characters.';
                } elseif (!hash_equals($password, $confirmation)) {
                    $formError = 'Your passwords do not match yet.';
                } else {
                    $statement = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, account_type) VALUES (:full_name, :email, :password_hash, :role, :account_type)');
                    $statement->execute([
                        'full_name' => $fullName,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => $role,
                        'account_type' => $accountType,
                    ]);
                    $formSuccess = 'Your account request was created. An administrator must approve it before you can log in.';
                }
            } else {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
                    $formError = 'Enter your email address and password.';
                } else {
                    $statement = $pdo->prepare('SELECT user_id, full_name, email, password_hash, role, account_type, account_status FROM users WHERE email = :email LIMIT 1');
                    $statement->execute(['email' => $email]);
                    $user = $statement->fetch();
                    if (!$user || !password_verify($password, $user['password_hash'])) {
                        $formError = 'The email or password is incorrect.';
                    } elseif ($user['account_status'] !== 'active') {
                        $formError = 'This account is waiting for admin approval.';
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['user'] = [
                            'user_id' => (int) $user['user_id'],
                            'full_name' => $user['full_name'],
                            'email' => $user['email'],
                            'role' => $user['role'],
                            'account_type' => $user['account_type'],
                        ];
                        unset($_SESSION['auth_csrf']);
                        $isDriver = in_array($user['role'], ['student', 'staff'], true) && $user['account_type'] === 'driver';
                        $destination = $user['role'] === 'admin' ? 'admin.php' : ($isDriver ? 'driver.php' : 'dashboard.php');
                        header('Location: ' . $destination);
                        exit;
                    }
                }
            }
        } catch (PDOException $exception) {
            if ($isSignup && $exception->getCode() === '23000') {
                $formError = 'An account with this email already exists.';
            } else {
                error_log('RideUTeM database error: ' . $exception->getMessage());
                $formError = 'Could not use the workshop database or users table. Check the MySQL connection settings and import database/schema.sql.';
            }
        }
    }
}

require dirname(__DIR__) . '/views/auth-page.php';
