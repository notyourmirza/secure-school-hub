<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../database.php';
require_once __DIR__ . '/../../auth.php';

send_security_headers('admin');
start_admin_session();

/** Counts rows for the current tenant. $table comes from a fixed allowlist. */
function count_tenant(string $table, string $where = '1=1', array $params = []): int
{
    $allowed = ['students', 'teachers', 'classes', 'attendance', 'notices', 'admissions'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Invalid table');
    }
    $stmt = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE school_id = :school_id AND {$where}");
    $stmt->execute(['school_id' => tenant_id()] + $params);
    return (int)$stmt->fetchColumn();
}

function plan_limit(string $metric): ?int
{
    $columns = ['students' => 'max_students', 'teachers' => 'max_users', 'staff' => 'max_users'];
    if (!isset($columns[$metric])) {
        return null;
    }
    $stmt = db()->prepare("SELECT sp.{$columns[$metric]} FROM subscriptions s JOIN subscription_plans sp ON sp.id = s.plan_id WHERE s.school_id = :school_id AND s.status IN ('trial','active') AND (s.expires_at IS NULL OR s.expires_at > NOW()) ORDER BY s.id DESC LIMIT 1");
    $stmt->execute(['school_id' => tenant_id()]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int)$value;
}

function require_plan_limit(string $metric, string $table): void
{
    $limit = plan_limit($metric);
    if ($limit !== null && count_tenant($table, 'deleted_at IS NULL') >= $limit) {
        flash('error', "Your subscription limit for {$metric} has been reached. Upgrade your plan to continue.");
        redirect('students.php');
    }
}
