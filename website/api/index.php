<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Config/config.php';
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Http/Response.php';
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Support/RateLimiter.php';
require_once __DIR__ . '/../src/Http/ModuleRouter.php';
require_once __DIR__ . '/../src/Http/TeacherRouter.php';

use SchoolSaaS\Auth\Auth;
use SchoolSaaS\Database\Database;
use SchoolSaaS\Http\Response;
use SchoolSaaS\Support\RateLimiter;
use SchoolSaaS\Http\ModuleRouter;
use SchoolSaaS\Http\TeacherRouter;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = preg_replace('#^/index\.php#', '', $path) ?: '/';

try {
    $db = Database::connection();

    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($method === 'GET' && $path === '/api/v1/health') {
        Response::json(true, 'API is healthy', ['database' => 'connected']);
    }

    if ($method === 'POST' && $path === '/api/v1/auth/login') {
        RateLimiter::check('login:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 8, 60);
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $email = trim((string)($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');
        if ($email === '' || $password === '') {
            Response::error('Email and password are required', 'VALIDATION_ERROR', 422);
        }
        $stmt = $db->prepare('SELECT u.*, r.name AS role_name, s.uuid AS school_uuid, s.name AS school_name, s.status AS school_status FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN schools s ON s.id = u.school_id WHERE u.email = :email AND u.status = "active" LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::error('Invalid credentials', 'AUTH_INVALID', 401);
        }
        if ($user['school_id'] !== null && !in_array($user['school_status'], ['trial', 'active'], true)) {
            Response::error('School account is not active', 'SCHOOL_INACTIVE', 403);
        }
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $expires = (new DateTimeImmutable('+8 hours'))->format('Y-m-d H:i:s');
        $insert = $db->prepare('INSERT INTO access_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)');
        $insert->execute(['user_id' => $user['id'], 'token_hash' => $tokenHash, 'expires_at' => $expires]);
        $permissionStmt = $db->prepare('SELECT p.name FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :role_id ORDER BY p.name');
        $permissionStmt->execute(['role_id' => $user['role_id']]);
        $permissions = array_values($permissionStmt->fetchAll(PDO::FETCH_COLUMN));
        $refreshPlain = bin2hex(random_bytes(32));
        $refreshExpires = (new DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');
        $refresh = $db->prepare('INSERT INTO refresh_tokens (user_id, token_hash, family_uuid, expires_at) VALUES (:user_id, :token_hash, :family_uuid, :expires_at)');
        $refresh->execute(['user_id' => $user['id'], 'token_hash' => hash('sha256', $refreshPlain), 'family_uuid' => uuid(), 'expires_at' => $refreshExpires]);
        Response::json(true, 'Login successful', ['token' => $plainToken, 'refresh_token' => $refreshPlain, 'expires_at' => $expires, 'user' => ['uuid' => $user['uuid'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role_name'], 'school' => $user['school_name'], 'school_uuid' => $user['school_uuid'], 'permissions' => $permissions]]);
    }

    if ($method === 'POST' && $path === '/api/v1/auth/refresh') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $refreshPlain = trim((string)($body['refresh_token'] ?? ''));
        if ($refreshPlain === '') Response::error('refresh_token is required', 'VALIDATION_ERROR', 422);
        $stmt = $db->prepare('SELECT r.*, u.uuid, u.name, u.email, u.school_id, u.role_id, ro.name AS role_name, s.uuid AS school_uuid, s.name AS school_name FROM refresh_tokens r JOIN users u ON u.id = r.user_id JOIN roles ro ON ro.id = u.role_id LEFT JOIN schools s ON s.id = u.school_id WHERE r.token_hash = :token_hash AND r.revoked_at IS NULL AND r.expires_at > NOW() AND u.status = "active" LIMIT 1');
        $stmt->execute(['token_hash' => hash('sha256', $refreshPlain)]);
        $user = $stmt->fetch();
        if (!$user) Response::error('Invalid refresh token', 'AUTH_REFRESH_INVALID', 401);
        $db->prepare('UPDATE refresh_tokens SET revoked_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]);
        $newToken = bin2hex(random_bytes(32));
        $newExpires = (new DateTimeImmutable('+8 hours'))->format('Y-m-d H:i:s');
        $db->prepare('INSERT INTO access_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)')->execute(['user_id' => $user['user_id'], 'token_hash' => hash('sha256', $newToken), 'expires_at' => $newExpires]);
        Response::json(true, 'Token refreshed', ['token' => $newToken, 'expires_at' => $newExpires]);
    }

    if ($method === 'POST' && $path === '/api/v1/auth/logout') {
        $user = Auth::requireUser($db);
        $token = Auth::bearerToken();
        $stmt = $db->prepare('UPDATE access_tokens SET revoked_at = NOW() WHERE token_hash = :token_hash AND user_id = :user_id');
        $stmt->execute(['token_hash' => hash('sha256', $token), 'user_id' => $user['id']]);
        Response::json(true, 'Logout successful');
    }

    if ($method === 'POST' && $path === '/api/v1/sync/push') {
        $user = Auth::requireUser($db);
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $operations = $body['operations'] ?? [];
        if (!is_array($operations) || count($operations) > 100) {
            Response::error('operations must be an array with at most 100 items', 'VALIDATION_ERROR', 422);
        }

        $results = [];
        foreach ($operations as $operation) {
            $operationUuid = trim((string)($operation['operation_uuid'] ?? ''));
            $clientUuid = trim((string)($operation['client_uuid'] ?? ''));
            $entity = trim((string)($operation['entity'] ?? ''));
            $operationType = trim((string)($operation['operation_type'] ?? ''));
            $payload = is_array($operation['payload'] ?? null) ? $operation['payload'] : [];
            if ($operationUuid === '' || $clientUuid === '' || $entity === '' || $operationType === '') {
                $results[] = ['operation_uuid' => $operationUuid, 'status' => 'failed', 'error_code' => 'VALIDATION_ERROR'];
                continue;
            }

            $existing = $db->prepare('SELECT operation_uuid, client_uuid, canonical_id, status, response_json FROM sync_operations WHERE operation_uuid = :operation_uuid AND school_id = :school_id LIMIT 1');
            $existing->execute(['operation_uuid' => $operationUuid, 'school_id' => $user['school_id']]);
            $previous = $existing->fetch();
            if ($previous && $previous['response_json']) {
                $results[] = json_decode((string)$previous['response_json'], true) ?: ['operation_uuid' => $operationUuid, 'status' => $previous['status'], 'canonical_id' => $previous['canonical_id']];
                continue;
            }

            if (!(($entity === 'students' || $entity === 'attendance') && $operationType === 'create')) {
                $results[] = ['operation_uuid' => $operationUuid, 'client_uuid' => $clientUuid, 'status' => 'failed', 'error_code' => 'SYNC_OPERATION_UNSUPPORTED'];
                continue;
            }

            try {
                Auth::requirePermission($db, $entity === 'attendance' ? 'attendance.create' : 'students.create', $user);
                $db->beginTransaction();
                $insertOperation = $db->prepare('INSERT INTO sync_operations (operation_uuid, client_uuid, school_id, user_id, entity, entity_id, operation_type, payload, status) VALUES (:operation_uuid, :client_uuid, :school_id, :user_id, :entity, :entity_id, :operation_type, :payload, \'pending\')');
                $insertOperation->execute(['operation_uuid' => $operationUuid, 'client_uuid' => $clientUuid, 'school_id' => $user['school_id'], 'user_id' => $user['id'], 'entity' => $entity, 'entity_id' => $clientUuid, 'operation_type' => $operationType, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);

                if ($entity === 'attendance') {
                    $attendanceLookup = $db->prepare('SELECT id FROM attendance WHERE client_uuid = :client_uuid AND school_id = :school_id LIMIT 1');
                    $attendanceLookup->execute(['client_uuid' => $clientUuid, 'school_id' => $user['school_id']]);
                    $attendance = $attendanceLookup->fetch();
                    if ($attendance) {
                        $canonicalId = (string)$attendance['id'];
                    } else {
                        $studentId = (int)($payload['student_id'] ?? 0);
                        $attendanceDate = trim((string)($payload['attendance_date'] ?? ''));
                        $attendanceStatus = trim((string)($payload['status'] ?? ''));
                        if ($studentId < 1 || $attendanceDate === '' || $attendanceStatus === '') throw new InvalidArgumentException('student_id, attendance_date and status are required');
                        $createAttendance = $db->prepare('INSERT INTO attendance (uuid, school_id, student_id, attendance_date, status, notes, client_uuid) VALUES (:uuid, :school_id, :student_id, :attendance_date, :status, :notes, :client_uuid) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
                        $createAttendance->execute(['uuid' => $clientUuid, 'school_id' => $user['school_id'], 'student_id' => $studentId, 'attendance_date' => $attendanceDate, 'status' => $attendanceStatus, 'notes' => $payload['notes'] ?? null, 'client_uuid' => $clientUuid]);
                        $canonicalId = (string)$db->lastInsertId();
                    }
                } else {
                $studentLookup = $db->prepare('SELECT id FROM students WHERE uuid = :client_uuid AND school_id = :school_id LIMIT 1');
                $studentLookup->execute(['client_uuid' => $clientUuid, 'school_id' => $user['school_id']]);
                $student = $studentLookup->fetch();
                if ($student) {
                    $canonicalId = (string)$student['id'];
                } else {
                    $admissionNo = trim((string)($payload['admission_no'] ?? ''));
                    $firstName = trim((string)($payload['first_name'] ?? ''));
                    if ($admissionNo === '' || $firstName === '') {
                        throw new InvalidArgumentException('admission_no and first_name are required');
                    }
                    $createStudent = $db->prepare('INSERT INTO students (uuid, school_id, admission_no, first_name, last_name, gender, date_of_birth, phone, address) VALUES (:uuid, :school_id, :admission_no, :first_name, :last_name, :gender, :date_of_birth, :phone, :address)');
                    $createStudent->execute(['uuid' => $clientUuid, 'school_id' => $user['school_id'], 'admission_no' => $admissionNo, 'first_name' => $firstName, 'last_name' => $payload['last_name'] ?? null, 'gender' => $payload['gender'] ?? null, 'date_of_birth' => $payload['date_of_birth'] ?? null, 'phone' => $payload['phone'] ?? null, 'address' => $payload['address'] ?? null]);
                    $canonicalId = (string)$db->lastInsertId();
                }
                }

                $result = ['operation_uuid' => $operationUuid, 'client_uuid' => $clientUuid, 'status' => 'applied', 'canonical_id' => $canonicalId];
                $updateOperation = $db->prepare('UPDATE sync_operations SET entity_id = :entity_id, canonical_id = :canonical_id, status = \'applied\', response_json = :response_json, processed_at = NOW() WHERE operation_uuid = :operation_uuid AND school_id = :school_id');
                $updateOperation->execute(['entity_id' => $canonicalId, 'canonical_id' => $canonicalId, 'response_json' => json_encode($result, JSON_THROW_ON_ERROR), 'operation_uuid' => $operationUuid, 'school_id' => $user['school_id']]);
                $db->commit();
                $results[] = $result;
            } catch (Throwable $syncError) {
                if ($db->inTransaction()) $db->rollBack();
                $results[] = ['operation_uuid' => $operationUuid, 'client_uuid' => $clientUuid, 'status' => 'failed', 'error_code' => $syncError instanceof InvalidArgumentException ? 'VALIDATION_ERROR' : 'SYNC_FAILED', 'message' => $syncError->getMessage()];
            }
        }
        Response::json(true, 'Sync processed', ['results' => $results]);
    }

    if ($method === 'GET' && $path === '/api/v1/sync/pull') {
        $user = Auth::requireUser($db);
        $cursor = max(0, (int)($_GET['cursor'] ?? 0));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $stmt = $db->prepare('SELECT id, operation_uuid, client_uuid, entity, entity_id, canonical_id, operation_type, status, payload, response_json, processed_at FROM sync_operations WHERE school_id = :school_id AND id > :cursor ORDER BY id ASC LIMIT ' . $limit);
        $stmt->execute(['school_id' => $user['school_id'], 'cursor' => $cursor]);
        $changes = [];
        $nextCursor = $cursor;
        foreach ($stmt->fetchAll() as $row) {
            $nextCursor = max($nextCursor, (int)$row['id']);
            $changes[] = [
                'cursor' => (int)$row['id'],
                'operation_uuid' => $row['operation_uuid'],
                'client_uuid' => $row['client_uuid'],
                'entity' => $row['entity'],
                'entity_id' => $row['entity_id'],
                'canonical_id' => $row['canonical_id'],
                'operation_type' => $row['operation_type'],
                'status' => $row['status'],
                'payload' => json_decode((string)$row['payload'], true) ?: [],
                'result' => $row['response_json'] ? (json_decode((string)$row['response_json'], true) ?: []) : null,
                'processed_at' => $row['processed_at'],
            ];
        }
        Response::json(true, 'Sync changes loaded', ['cursor' => $nextCursor, 'has_more' => count($changes) === $limit, 'changes' => $changes]);
    }

    if ($method === 'GET' && $path === '/api/v1/sync/status') {
        $user = Auth::requireUser($db);
        $stmt = $db->prepare('SELECT COALESCE(MAX(id), 0) FROM sync_operations WHERE school_id = :school_id');
        $stmt->execute(['school_id' => $user['school_id']]);
        Response::json(true, 'Sync status loaded', ['cursor' => (int)$stmt->fetchColumn()]);
    }

    if (TeacherRouter::dispatch($db, $method, $path)) { exit; }

    // Native teacher app endpoints. All reads derive school_id and user identity from the bearer token.
    if (preg_match('#^/api/v1/teacher/(dashboard|profile|classes|students|timetable|notices|notifications)$#', $path, $teacherMatch) && $method === 'GET') {
        $teacher = Auth::requireUser($db);
        if ($teacher['role_name'] !== 'teacher' && $teacher['role_name'] !== 'school_admin' && $teacher['role_name'] !== 'super_admin') Response::error('Teacher access required', 'FORBIDDEN', 403);
        $resource = $teacherMatch[1]; $schoolId = (int)$teacher['school_id'];
        if ($resource === 'dashboard') {
            $stmt = $db->prepare('SELECT COUNT(*) FROM timetable_entries WHERE school_id=:school_id AND teacher_id=:teacher_id AND weekday=DAYOFWEEK(CURDATE())-1'); $stmt->execute(['school_id'=>$schoolId,'teacher_id'=>$teacher['id']]);
            Response::json(true, 'Teacher dashboard loaded', ['teacher'=>['name'=>$teacher['name'],'email'=>$teacher['email']],'today_classes'=>(int)$stmt->fetchColumn(),'date'=>date('Y-m-d')]);
        }
        if ($resource === 'profile') Response::json(true, 'Teacher profile loaded', ['uuid'=>$teacher['uuid'],'name'=>$teacher['name'],'email'=>$teacher['email'],'role'=>$teacher['role_name']]);
        $queries = ['classes'=>'SELECT id,name,status FROM classes WHERE school_id=:school_id ORDER BY name','students'=>'SELECT id,uuid,admission_no,first_name,last_name,status FROM students WHERE school_id=:school_id AND deleted_at IS NULL ORDER BY first_name,last_name','timetable'=>'SELECT id,class_id,subject_id,teacher_id,weekday,starts_at,ends_at,room FROM timetable_entries WHERE school_id=:school_id AND teacher_id=:teacher_id ORDER BY weekday,starts_at','notices'=>'SELECT id,uuid,title,body,published_at,status FROM notices WHERE school_id=:school_id AND status="published" ORDER BY published_at DESC LIMIT 50','notifications'=>'SELECT id,title,body,created_at FROM notifications WHERE school_id=:school_id ORDER BY id DESC LIMIT 50'];
        if (!isset($queries[$resource])) Response::error('Resource not found', 'NOT_FOUND', 404);
        $stmt=$db->prepare($queries[$resource]); $params=['school_id'=>$schoolId]; if (in_array($resource,['timetable'],true)) $params['teacher_id']=$teacher['id']; $stmt->execute($params); Response::json(true, ucfirst($resource).' loaded', ['items'=>$stmt->fetchAll()]);
    }

    if (ModuleRouter::dispatch($db, $method, $path)) {
        exit;
    }

    if (preg_match('#^/api/v1/students(?:/([0-9]+))?$#', $path, $matches)) {
        $user = Auth::requirePermission($db, 'students.view');
        $studentId = $matches[1] ?? null;
        if ($method === 'GET') {
            if ($studentId !== null) {
                $stmt = $db->prepare('SELECT * FROM students WHERE id = :id AND school_id = :school_id AND deleted_at IS NULL');
                $stmt->execute(['id' => $studentId, 'school_id' => $user['school_id']]);
                $student = $stmt->fetch();
                if (!$student) Response::error('Student not found', 'NOT_FOUND', 404);
                Response::json(true, 'Student loaded', $student);
            }
            $stmt = $db->prepare('SELECT * FROM students WHERE school_id = :school_id AND deleted_at IS NULL ORDER BY id DESC');
            $stmt->execute(['school_id' => $user['school_id']]);
            Response::json(true, 'Students loaded', $stmt->fetchAll());
        }
        if ($method === 'POST') {
            Auth::requirePermission($db, 'students.create', $user);
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $admissionNo = trim((string)($body['admission_no'] ?? ''));
            $firstName = trim((string)($body['first_name'] ?? ''));
            if ($admissionNo === '' || $firstName === '') Response::error('admission_no and first_name are required', 'VALIDATION_ERROR', 422);
            $stmt = $db->prepare('INSERT INTO students (uuid, school_id, admission_no, first_name, last_name, gender, date_of_birth, phone, address) VALUES (:uuid, :school_id, :admission_no, :first_name, :last_name, :gender, :date_of_birth, :phone, :address)');
            $stmt->execute(['uuid' => uuid(), 'school_id' => $user['school_id'], 'admission_no' => $admissionNo, 'first_name' => $firstName, 'last_name' => $body['last_name'] ?? null, 'gender' => $body['gender'] ?? null, 'date_of_birth' => $body['date_of_birth'] ?? null, 'phone' => $body['phone'] ?? null, 'address' => $body['address'] ?? null]);
            Response::json(true, 'Student created successfully', ['id' => (int)$db->lastInsertId()], 201);
        }
    }

    Response::error('Route not found', 'NOT_FOUND', 404);
} catch (Throwable $e) {
    error_log($e->getMessage());
    Response::error('Internal server error', 'SERVER_ERROR', 500);
}

function uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
