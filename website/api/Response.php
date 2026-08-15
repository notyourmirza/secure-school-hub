<?php
declare(strict_types=1);

namespace SchoolSaaS\Http;

final class Response
{
    public static function json(bool $success, string $message, mixed $data = null, int $status = 200): never
    {
        http_response_code($status);
        $payload = ['success' => $success, 'message' => $message];
        if ($data !== null) $payload['data'] = $data;
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, string $code, int $status): never
    {
        http_response_code($status);
        echo json_encode(['success' => false, 'message' => $message, 'error_code' => $code], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
