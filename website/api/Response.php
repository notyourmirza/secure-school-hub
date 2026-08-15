<?php
declare(strict_types=1);

namespace SchoolSaaS\Http;

/**
 * Consistent JSON envelope. Responses never contain stack traces, SQL errors
 * or filesystem paths; detail is written to the server error log instead.
 */
final class Response
{
    public static function json(bool $success, string $message, mixed $data = null, int $status = 200): never
    {
        self::emit($status, array_filter([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], static fn ($value): bool => $value !== null));
    }

    public static function error(string $message, string $code, int $status): never
    {
        self::emit($status, ['success' => false, 'message' => $message, 'error_code' => $code]);
    }

    private static function emit(int $status, array $payload): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
