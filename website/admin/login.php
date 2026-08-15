<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user()) redirect('index.php');
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)post_value('email',''))); $password = (string)($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') $error = 'Enter a valid email and password.';
    else {
        $stmt = db()->prepare('SELECT u.*, r.name AS role_name, s.name AS school_name, s.status AS school_status FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN schools s ON s.id=u.school_id WHERE LOWER(u.email)=:email AND u.status="active" LIMIT 1'); $stmt->execute(['email'=>$email]); $user=$stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) $error='Invalid credentials.';
        elseif ($user['school_id'] !== null && !in_array($user['school_status'], ['trial','active'], true)) $error='This school account is not active.';
        else { session_regenerate_id(true); unset($user['password_hash']); $_SESSION['admin_user']=$user; audit('login','user',(string)$user['id']); redirect('index.php'); }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · School SaaS</title><link rel="stylesheet" href="assets/admin.css"></head><body><div class="login-shell"><section class="login-card"><p class="eyebrow">School SaaS</p><h1>Welcome back</h1><p>Sign in to manage your school workspace.</p><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><div class="field"><label for="email">Email</label><input id="email" type="email" name="email" autocomplete="username" required></div><div class="field"><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required></div><button class="btn" type="submit">Sign in</button></form></section></div></body></html>
