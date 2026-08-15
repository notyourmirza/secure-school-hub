<?php
declare(strict_types=1);

require_once __DIR__ . '/src/Config/config.php';
require_once __DIR__ . '/src/Database/Database.php';

use SchoolSaaS\Database\Database;

$db = Database::connection();
$db->beginTransaction();
try {
    $school = $db->prepare('INSERT INTO schools (uuid, code, name, status) VALUES (:uuid, :code, :name, "active") ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    $school->execute(['uuid' => uuid(), 'code' => 'DEMO', 'name' => 'Demo School']);
    $schoolId = (int)$db->lastInsertId();

    $role = $db->prepare('SELECT id FROM roles WHERE name = "school_admin"');
    $role->execute();
    $roleId = (int)$role->fetchColumn();

    $user = $db->prepare('INSERT INTO users (uuid, school_id, role_id, name, email, password_hash) VALUES (:uuid, :school_id, :role_id, :name, :email, :password_hash) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), password_hash = VALUES(password_hash), role_id = VALUES(role_id)');
    $user->execute(['uuid' => uuid(), 'school_id' => $schoolId, 'role_id' => $roleId, 'name' => 'School Administrator', 'email' => 'admin@example.com', 'password_hash' => password_hash('ChangeMe123!', PASSWORD_DEFAULT)]);

    $permissions = $db->query('SELECT id FROM permissions')->fetchAll(PDO::FETCH_COLUMN);
    $grant = $db->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)');
    foreach ($permissions as $permissionId) $grant->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
    $db->commit();
    echo "Seeded admin@example.com / ChangeMe123!\n";
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

function uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
