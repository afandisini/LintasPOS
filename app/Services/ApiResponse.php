<?php

declare(strict_types=1);

namespace App\Services;

use System\Http\Response;

final class ApiResponse
{
    public static function success(mixed $data = null, int $status = 200, array $meta = []): Response
    {
        $body = ['success' => true, 'data' => $data];
        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return Response::json($body, $status);
    }

    public static function error(string $code, string $message, int $status, array $details = []): Response
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }

        return Response::json(['success' => false, 'error' => $error], $status);
    }
}
