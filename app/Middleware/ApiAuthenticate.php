<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\ApiResponse;
use App\Services\ApiTokenService;
use System\Http\Request;

final class ApiAuthenticate
{
    public function handle(Request $request, callable $next): mixed
    {
        $header = trim((string) $request->header('Authorization', ''));
        if (!preg_match('/^Bearer\s+([^\s]+)$/i', $header, $matches)) {
            return ApiResponse::error('UNAUTHENTICATED', 'Bearer token wajib diisi.', 401);
        }

        $user = (new ApiTokenService())->find($matches[1]);
        if ($user === null) {
            return ApiResponse::error('INVALID_TOKEN', 'Bearer token tidak valid atau sudah kedaluwarsa.', 401);
        }

        $request->setAttribute('api_user', $user);
        $request->setAttribute('api_token', $matches[1]);
        return $next($request);
    }
}
