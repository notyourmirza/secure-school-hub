<?php
/**
 * School SaaS — central configuration.
 *
 * No credential is hardcoded here. Values come from real environment variables
 * (preferred) or from an untracked website/.env file. See config.example.php.
 */
declare(strict_types=1);

if (defined('SCHOOL_SAAS_CONFIG_LOADED')) {
    return;
}
define('SCHOOL_SAAS_CONFIG_LOADED', true);

/** Load website/.env once, without overriding real environment variables. */
(static function (): void {
    $file = __DIR__ . '/.env';
    if (!is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) > 1 && ($value[0] === '"' || $value[0] === "'") && $value[0] === substr($value, -1)) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
})();

function env_value(string $key, ?string $default = null): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        if ($default === null) {
            // Fail closed: never fall back to a guessable credential.
            error_log('Missing required configuration value: ' . $key);
            throw new RuntimeException('Application is not configured correctly.');
        }
        return $default;
    }
    return $value;
}

define('APP_ENV', env_value('APP_ENV', 'production'));
define('APP_DEBUG', filter_var(env_value('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL));
define('APP_BASE_URL', rtrim(env_value('APP_BASE_URL', 'https://localhost'), '/'));

define('DB_HOST', env_value('DB_HOST', '127.0.0.1'));
define('DB_PORT', env_value('DB_PORT', '3306'));
define('DB_NAME', env_value('DB_NAME', 'school_saas'));
define('DB_USER', env_value('DB_USER', 'school_saas'));
// No default: an unconfigured deployment must fail instead of using a known password.
define('DB_PASSWORD', APP_ENV === 'testing' ? env_value('DB_PASSWORD', '') : env_value('DB_PASSWORD'));

/** Comma separated browser origins allowed to call the API. Empty = no CORS at all. */
define('APP_ORIGINS', env_value('APP_ORIGINS', ''));

// Input limits (bytes / characters) applied by the API and the admin panel.
define('MAX_JSON_BODY_BYTES', (int)env_value('MAX_JSON_BODY_BYTES', '262144'));
define('MAX_NAME_LENGTH', 120);
define('MAX_EMAIL_LENGTH', 190);
define('MAX_PHONE_LENGTH', 32);
define('MAX_SEARCH_LENGTH', 80);
define('MAX_TEXT_LENGTH', 5000);

// Session lifetimes (seconds).
define('SESSION_IDLE_TIMEOUT', (int)env_value('SESSION_IDLE_TIMEOUT', '1800'));
define('SESSION_ABSOLUTE_TIMEOUT', (int)env_value('SESSION_ABSOLUTE_TIMEOUT', '43200'));

// Production must never leak stack traces, SQL errors or file paths to clients.
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** Baseline security headers shared by the API and the admin panel. */
function send_security_headers(string $context = 'api'): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header_remove('X-Powered-By');
    if (request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    if ($context === 'admin') {
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    } else {
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
    }
}

/**
 * Strict CORS: only origins explicitly listed in APP_ORIGINS are echoed back.
 * Native Android/Windows clients are not browsers and need no CORS at all,
 * so the default configuration allows nothing.
 */
function apply_cors_policy(): void
{
    $allowed = array_values(array_filter(array_map('trim', explode(',', APP_ORIGINS))));
    if (!$allowed) {
        return;
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    header('Vary: Origin');
    if ($origin === '' || !in_array($origin, $allowed, true)) {
        return;
    }
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Max-Age: 600');
}

function uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
