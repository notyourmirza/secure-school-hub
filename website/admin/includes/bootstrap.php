<?php
declare(strict_types=1);

session_name('school_saas_admin');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
session_start();

require_once __DIR__ . '/../../backend/src/Config/config.php';
require_once __DIR__ . '/../../backend/src/Database/Database.php';

use SchoolSaaS\Database\Database;

function db(): PDO { return Database::connection(); }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verify_csrf(): void {
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['_csrf'] ?? ''))) { http_response_code(419); exit('Invalid CSRF token'); }
}
function flash(string $type, string $message): void { $_SESSION['flash'] = [$type, $message]; }
function take_flash(): ?array { $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $flash; }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function current_user(): ?array { return $_SESSION['admin_user'] ?? null; }
function require_login(): array { $user = current_user(); if (!$user) redirect('login.php'); return $user; }
function require_role(array $roles): array { $user = require_login(); if (!in_array($user['role_name'], $roles, true)) { http_response_code(403); exit('Forbidden'); } return $user; }
function tenant_id(): int { $user = require_login(); if ($user['school_id'] === null) { http_response_code(403); exit('Tenant context required'); } return (int)$user['school_id']; }
function post_value(string $key, mixed $default = null): mixed { return array_key_exists($key, $_POST) ? trim((string)$_POST[$key]) : $default; }
function count_tenant(string $table, string $where = '1=1', array $params = []): int { $allowed = ['students','teachers','classes','attendance','notices','admissions']; if (!in_array($table, $allowed, true)) throw new InvalidArgumentException('Invalid table'); $stmt = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE school_id = :school_id AND {$where}"); $stmt->execute(['school_id' => tenant_id()] + $params); return (int)$stmt->fetchColumn(); }
function plan_limit(string $metric): ?int { $columns = ['students'=>'max_students','teachers'=>'max_users','staff'=>'max_users']; if (!isset($columns[$metric])) return null; $stmt = db()->prepare("SELECT sp.{$columns[$metric]} FROM subscriptions s JOIN subscription_plans sp ON sp.id=s.plan_id WHERE s.school_id=:school_id AND s.status IN ('trial','active') AND (s.expires_at IS NULL OR s.expires_at>NOW()) ORDER BY s.id DESC LIMIT 1"); $stmt->execute(['school_id'=>tenant_id()]); $value = $stmt->fetchColumn(); return $value === false ? null : (int)$value; }
function require_plan_limit(string $metric, string $table): void { $limit = plan_limit($metric); if ($limit !== null && count_tenant($table, 'deleted_at IS NULL') >= $limit) { flash('error', "Your subscription limit for {$metric} has been reached. Upgrade your plan to continue."); redirect('students.php'); } }
function audit(string $action, string $entity, ?string $entityId = null): void { $user = current_user(); if (!$user) return; $stmt = db()->prepare('INSERT INTO audit_logs (school_id,user_id,action,entity,entity_id,ip_address) VALUES (:school_id,:user_id,:action,:entity,:entity_id,:ip)'); $stmt->execute(['school_id'=>$user['school_id'],'user_id'=>$user['id'],'action'=>$action,'entity'=>$entity,'entity_id'=>$entityId,'ip'=>$_SERVER['REMOTE_ADDR'] ?? null]); }
