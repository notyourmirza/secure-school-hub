<?php
declare(strict_types=1);

namespace SchoolSaaS\Database;

use PDO;
use PDOException;

require_once __DIR__ . '/config.php';

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            self::$connection = new PDO($dsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real server-side prepared statements: values can never be
                // re-interpreted as SQL.
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $e) {
            // Never surface DSN, credentials or driver detail to the caller.
            error_log('Database connection failed: ' . $e->getMessage());
            throw new PDOException('Database connection failed');
        }
        return self::$connection;
    }

    /** Escape %, _ and \ so user input cannot widen a LIKE pattern. */
    public static function likeParam(string $value): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value) . '%';
    }

    /** Only ever place identifiers in SQL through an explicit allowlist. */
    public static function allowlisted(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    public static function sortDirection(?string $value): string
    {
        return strtoupper((string)$value) === 'ASC' ? 'ASC' : 'DESC';
    }
}
