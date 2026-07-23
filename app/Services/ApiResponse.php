<?php

declare(strict_types=1);

namespace App\Services;

use System\Http\Response;

final class ApiResponse
{
    public static function success(mixed $data = null, int $status = 200, array $meta = []): Response
    {
        $meta = self::meta($meta);
        $body = ['success' => true, 'message' => 'OK', 'data' => $data, 'meta' => $meta];

        return self::response($body, $status);
    }

    public static function error(string $code, string $message, int $status, array $details = []): Response
    {
        $errors = $details !== [] ? $details : [$code => [$message]];
        $body = ['success' => false, 'message' => $message, 'errors' => $errors, 'meta' => self::meta()];
        $body['error'] = ['code' => $code, 'message' => $message];

        return self::response($body, $status);
    }

    private static function response(array $body, int $status): Response
    {
        return Response::json($body, $status, [
            'X-Request-Id' => (string) $body['meta']['request_id'],
            'X-API-Version' => '1',
            'Cache-Control' => 'no-store',
        ]);
    }

    private static function meta(array $meta = []): array
    {
        $requestId = (string) ($_SERVER['_SECURITY_REQUEST_ID'] ?? '');
        if ($requestId === '') {
            try {
                $requestId = bin2hex(random_bytes(16));
            } catch (\Throwable) {
                $requestId = uniqid('req_', true);
            }
            $_SERVER['_SECURITY_REQUEST_ID'] = $requestId;
        }

        return array_merge([
            'request_id' => $requestId,
            'timestamp' => date('c'),
        ], $meta);
    }
}
