<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\ApiResponse;
use System\Http\Request;
use System\Http\Response;

final class ApiCors
{
    /** @var array<int, string> */
    private const ALLOWED_ORIGINS = [
        'http://localhost',
        'https://localhost',
        'capacitor://localhost',
        'ionic://localhost',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'https://lintaspos.ddev.site',
        'https://lintaspos.co-id.id',
    ];

    public function handle(Request $request, callable $next): mixed
    {
        $origin = trim((string) $request->header('Origin', ''));
        if ($request->method() === 'OPTIONS') {
            if ($origin !== '' && !in_array($origin, self::ALLOWED_ORIGINS, true)) {
                return ApiResponse::error('CORS_ORIGIN_DENIED', 'Origin tidak diizinkan.', 403);
            }

            return $this->headers(Response::json([], 204), $origin);
        }

        $response = $next($request);
        if (!$response instanceof Response) {
            return $response;
        }

        return $this->headers($response, $origin);
    }

    private function headers(Response $response, string $origin): Response
    {
        $response = $response
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Accept, Authorization, Content-Type, X-Request-Id')
            ->withHeader('Access-Control-Max-Age', '86400')
            ->withHeader('Vary', 'Origin');

        if ($origin !== '' && in_array($origin, self::ALLOWED_ORIGINS, true)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        }

        return $response;
    }
}
