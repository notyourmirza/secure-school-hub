<?php
require_once __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
verify_csrf();
$user=current_user(); if($user) audit('logout','user',(string)$user['id']);
$_SESSION=[]; session_destroy(); redirect('login.php');
