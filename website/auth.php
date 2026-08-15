<?php
/**
 * Shared authentication, authorization, CSRF, rate limiting and input helpers
 * for both the API (bearer tokens) and the admin panel (PHP sessions).
 */
declare(strict_types=1);

namespace SchoolSaaS\Support {

    use PDO;

    /**
     * Persistent, MySQL backed rate limiter.
     * The previous in-process array limiter reset on every PHP request and
     * therefore provided no protection at all.
     */
    final class RateLimiter
    {
        public static function hit(PDO $db, string $bucket, string $identifier, int $limit, int $windowSeconds): bool
        {
            $key = substr($bucket . ':' . $identifier, 0, 190);
            try {
                $db->prepare('DELETE FROM rate_limits WHERE window_started_at < (NOW() - INTERVAL :window SECOND)')
                    ->execute(['window' => $windowSeconds]);
                $stmt = $db->prepare('SELECT id, hits FROM rate_limits WHERE rate_key = :k AND window_started_at >= (NOW() - INTERVAL :window SECOND) LIMIT 1');
                $stmt->execute(['k' => $key, 'window' => $windowSeconds]);
                $row = $stmt->fetch();
                if (!$row) {
                    $db->prepare('INSERT INTO rate_limits (rate_key, hits, window_started_at) VALUES (:k, 1, NOW()) ON DUPLICATE KEY UPDATE hits = 1, window_started_at = NOW()')
                        ->execute(['k' => $key]);
                    return true;
                }
                if ((int)$row['hits'] >= $limit) {
                    return false;
                }
                $db->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE id = :id')->execute(['id' => $row['id']]);
                return true;
            } catch (\Throwable $e) {
                error_log('Rate limiter failure: ' . $e->getMessage());
                // Fail closed for sensitive buckets.
                return false;
            }
        }

        public static function clear(PDO $db, string $bucket, string $identifier): void
        {
            try {
                $db->prepare('DELETE FROM rate_limits WHERE rate_key = :k')
                    ->execute(['k' => substr($bucket . ':' . $identifier, 0, 190)]);
            } catch (\Throwable $e) {
                error_log('Rate limiter cleanup failure: ' . $e->getMessage());
            }
        }
    }

    /** Brute-force protection for credential endpoints (API + admin). */
    final class LoginThrottle
    {
        private const MAX_ATTEMPTS = 8;
        private const WINDOW_MINUTES = 15;

        public static function blocked(PDO $db, string $email, string $ip): bool
        {
            try {
                $stmt = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE succeeded = 0 AND attempted_at > (NOW() - INTERVAL :minutes MINUTE) AND (email = :email OR ip_address = :ip)');
                $stmt->execute(['minutes' => self::WINDOW_MINUTES, 'email' => substr($email, 0, 190), 'ip' => substr($ip, 0, 45)]);
                return (int)$stmt->fetchColumn() >= self::MAX_ATTEMPTS;
            } catch (\Throwable $e) {
                error_log('Login throttle failure: ' . $e->getMessage());
                return false;
            }
        }

        public static function record(PDO $db, string $email, string $ip, bool $succeeded): void
        {
            try {
                $db->prepare('INSERT INTO login_attempts (email, ip_address, succeeded) VALUES (:email, :ip, :ok)')
                    ->execute(['email' => substr($email, 0, 190), 'ip' => substr($ip, 0, 45), 'ok' => $succeeded ? 1 : 0]);
                if ($succeeded) {
                    $db->prepare('DELETE FROM login_attempts WHERE succeeded = 0 AND email = :email')->execute(['email' => substr($email, 0, 190)]);
                }
            } catch (\Throwable $e) {
                error_log('Login attempt logging failure: ' . $e->getMessage());
            }
        }
    }
}

namespace SchoolSaaS\Auth {

    use PDO;
    use SchoolSaaS\Http\Response;

    final class Auth
    {
        public static function bearerToken(): string
        {
            $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
            if (preg_match('/^Bearer\s+([A-Za-z0-9._\-]{16,512})$/', (string)$header, $matches) !== 1) {
                Response::error('Authentication required', 'AUTH_REQUIRED', 401);
            }
            return trim($matches[1]);
        }

        /**
         * Resolves the caller purely from the bearer token. No client supplied
         * school_id, role or permission is ever trusted.
         */
        public static function requireUser(PDO $db): array
        {
            $hash = hash('sha256', self::bearerToken());
            $stmt = $db->prepare('SELECT u.*, r.name AS role_name FROM access_tokens t JOIN users u ON u.id = t.user_id JOIN roles r ON r.id = u.role_id WHERE t.token_hash = :token_hash AND t.revoked_at IS NULL AND t.expires_at > NOW() AND u.status = :active LIMIT 1');
            $stmt->execute(['token_hash' => $hash, 'active' => 'active']);
            $user = $stmt->fetch();
            if (!$user) {
                Response::error('Authentication required', 'AUTH_REQUIRED', 401);
            }
            unset($user['password_hash']);
            if ($user['school_id'] !== null) {
                $school = $db->prepare('SELECT status FROM schools WHERE id = :school_id LIMIT 1');
                $school->execute(['school_id' => $user['school_id']]);
                $status = $school->fetchColumn();
                if (!in_array($status, ['trial', 'active'], true)) {
                    Response::error('School account is not active', 'SCHOOL_INACTIVE', 403);
                }
            }
            return $user;
        }

        public static function hasPermission(PDO $db, array $user, string $permission): bool
        {
            $stmt = $db->prepare('SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :role_id AND p.name = :permission LIMIT 1');
            $stmt->execute(['role_id' => $user['role_id'], 'permission' => $permission]);
            return (bool)$stmt->fetchColumn();
        }

        public static function requirePermission(PDO $db, string $permission, ?array $user = null): array
        {
            $user ??= self::requireUser($db);
            if (!self::hasPermission($db, $user, $permission)) {
                Response::error('Permission denied', 'FORBIDDEN', 403);
            }
            // Tenant-scoped data requires a tenant context; only super admins
            // may exist without one, and they still cannot read tenant rows
            // through tenant-scoped routes.
            if ($user['school_id'] === null && $user['role_name'] !== 'super_admin') {
                Response::error('Tenant context required', 'TENANT_REQUIRED', 403);
            }
            return $user;
        }

        /** Server-derived tenant id. Never read school_id from the request. */
        public static function tenantId(array $user): int
        {
            if ($user['school_id'] === null) {
                Response::error('Tenant context required', 'TENANT_REQUIRED', 403);
            }
            return (int)$user['school_id'];
        }

        public static function revokeToken(PDO $db, string $plainToken): void
        {
            $stmt = $db->prepare('UPDATE access_tokens SET revoked_at = NOW() WHERE token_hash = :hash AND revoked_at IS NULL');
            $stmt->execute(['hash' => hash('sha256', $plainToken)]);
        }

        public static function audit(PDO $db, ?array $user, string $action, string $entity, ?string $entityId = null): void
        {
            try {
                $stmt = $db->prepare('INSERT INTO audit_logs (school_id, user_id, action, entity, entity_id, ip_address) VALUES (:school_id, :user_id, :action, :entity, :entity_id, :ip)');
                $stmt->execute([
                    'school_id' => $user['school_id'] ?? null,
                    'user_id' => $user['id'] ?? null,
                    'action' => $action,
                    'entity' => $entity,
                    'entity_id' => $entityId,
                    'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                ]);
            } catch (\Throwable $e) {
                error_log('Audit log failure: ' . $e->getMessage());
            }
        }
    }
}

namespace {

    use SchoolSaaS\Auth\Auth;
    use SchoolSaaS\Database\Database;

    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/database.php';

    /** HTML escaping for text nodes and attribute values. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Escaping for values interpolated into a URL. */
    function u(mixed $value): string
    {
        return rawurlencode((string)$value);
    }

    /** Escaping for values embedded in a JS context (JSON, HTML safe). */
    function js(mixed $value): string
    {
        return (string)json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
    }

    function clamp_text(mixed $value, int $max): string
    {
        $value = trim((string)$value);
        return mb_substr($value, 0, $max);
    }

    function db(): PDO
    {
        return Database::connection();
    }

    /**
     * Session bootstrap with secure cookie flags, idle timeout, absolute
     * timeout and fixation protection.
     */
    function start_admin_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('school_saas_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => request_is_https(),
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();

        $now = time();
        $started = (int)($_SESSION['_started_at'] ?? 0);
        $seen = (int)($_SESSION['_last_seen'] ?? 0);
        if ($started === 0) {
            $_SESSION['_started_at'] = $now;
        } elseif (($now - $started) > SESSION_ABSOLUTE_TIMEOUT || ($seen > 0 && ($now - $seen) > SESSION_IDLE_TIMEOUT)) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_started_at'] = $now;
            $_SESSION['expired'] = true;
        }
        $_SESSION['_last_seen'] = $now;
    }

    function csrf_token(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    /** Rejects any state-changing admin request without a valid CSRF token. */
    function verify_csrf(): void
    {
        $token = (string)($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($token === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $token)) {
            http_response_code(419);
            exit('Invalid or expired CSRF token. Reload the page and try again.');
        }
    }

    function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = [$type, $message];
    }

    function take_flash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    function redirect(string $url): never
    {
        // Only relative, in-app targets are ever emitted.
        if (preg_match('#^[A-Za-z0-9_./?=&%#-]+$#', $url) !== 1 || str_starts_with($url, '//')) {
            $url = 'index.php';
        }
        header('Location: ' . $url);
        exit;
    }

    function current_user(): ?array
    {
        return $_SESSION['admin_user'] ?? null;
    }

    function require_login(): array
    {
        $user = current_user();
        if (!$user) {
            redirect('login.php');
        }
        return $user;
    }

    function require_role(array $roles): array
    {
        $user = require_login();
        if (!in_array($user['role_name'], $roles, true)) {
            http_response_code(403);
            exit('Forbidden');
        }
        return $user;
    }

    /**
     * Admin authorization. Previously the admin pages only checked "is logged
     * in", so any authenticated role could open any module.
     */
    function require_permission(string $permission): array
    {
        $user = require_login();
        if ($user['role_name'] === 'super_admin') {
            return $user;
        }
        if (!Auth::hasPermission(db(), $user, $permission)) {
            http_response_code(403);
            exit('Forbidden: your role does not include ' . e($permission));
        }
        return $user;
    }

    /** Tenant id always comes from the authenticated session, never a request. */
    function tenant_id(): int
    {
        $user = require_login();
        if ($user['school_id'] === null) {
            http_response_code(403);
            exit('Tenant context required');
        }
        return (int)$user['school_id'];
    }

    function post_value(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $_POST) ? clamp_text($_POST[$key], MAX_TEXT_LENGTH) : $default;
    }

    function valid_date(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return ($date && $date->format('Y-m-d') === $value) ? $value : null;
    }

    function audit(string $action, string $entity, ?string $entityId = null): void
    {
        $user = current_user();
        if (!$user) {
            return;
        }
        Auth::audit(db(), $user, $action, $entity, $entityId);
    }
}
