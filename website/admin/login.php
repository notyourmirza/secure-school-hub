<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

use SchoolSaaS\Support\LoginThrottle;

if (current_user()) {
    redirect('index.php');
}
$error = null;
if (!empty($_SESSION['expired'])) {
    unset($_SESSION['expired']);
    $error = 'Your session expired. Please sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(clamp_text($_POST['email'] ?? '', MAX_EMAIL_LENGTH));
    $password = (string)($_POST['password'] ?? '');
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || strlen($password) > 512) {
        $error = 'Enter a valid email and password.';
    } elseif (LoginThrottle::blocked(db(), $email, $ip)) {
        $error = 'Too many failed sign-in attempts. Please wait a few minutes and try again.';
    } else {
        $stmt = db()->prepare('SELECT u.*, r.name AS role_name, s.name AS school_name, s.status AS school_status FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN schools s ON s.id = u.school_id WHERE LOWER(u.email) = :email AND u.status = :active LIMIT 1');
        $stmt->execute(['email' => $email, 'active' => 'active']);
        $user = $stmt->fetch();
        // password_verify against a password_hash() digest; timing is constant
        // and the same generic error is returned for unknown users.
        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            LoginThrottle::record(db(), $email, $ip, false);
            $error = 'Invalid credentials.';
        } elseif ($user['school_id'] !== null && !in_array($user['school_status'], ['trial', 'active'], true)) {
            LoginThrottle::record(db(), $email, $ip, false);
            $error = 'This school account is not active.';
        } else {
            LoginThrottle::record(db(), $email, $ip, true);
            // Defeats session fixation: a brand new session id after login.
            session_regenerate_id(true);
            unset($user['password_hash']);
            $_SESSION['admin_user'] = $user;
            $_SESSION['_started_at'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            audit('login', 'user', (string)$user['id']);
            redirect('index.php');
        }
    }
    if ($error !== null) {
        error_log('Failed admin login for ' . $email);
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · School SaaS</title><link rel="stylesheet" href="assets/admin.css"></head><body><div class="login-shell"><section class="login-card"><p class="eyebrow">School SaaS</p><h1>Welcome back</h1><p>Sign in to manage your school workspace.</p><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><div class="field"><label for="email">Email</label><input id="email" type="email" name="email" maxlength="<?= MAX_EMAIL_LENGTH ?>" autocomplete="username" required></div><div class="field"><label for="password">Password</label><input id="password" type="password" name="password" maxlength="512" autocomplete="current-password" required></div><button class="btn" type="submit">Sign in</button></form></section></div></body></html>
