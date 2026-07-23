<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\ApiResponse;
use App\Services\ApiTokenService;
use App\Services\AuthService;
use System\Http\Request;
use System\Http\Response;

final class AuthController
{
    public function login(Request $request, AuthService $auth, ApiTokenService $tokens): Response
    {
        $payload = $this->payload($request);
        $result = $auth->attempt(
            (string) ($payload['identity'] ?? $payload['username'] ?? $payload['email'] ?? ''),
            (string) ($payload['password'] ?? ''),
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );
        if (($result['ok'] ?? false) !== true || !is_array($result['user'] ?? null)) {
            return ApiResponse::error('INVALID_CREDENTIALS', (string) ($result['message'] ?? 'Kredensial tidak valid.'), 401);
        }

        $issued = $tokens->issue((int) $result['user']['id'], (string) ($payload['device_name'] ?? 'api-client'));
        return ApiResponse::success([
            'token' => $issued['token'],
            'token_type' => 'Bearer',
            'expires_at' => $issued['expires_at'],
            'user' => $result['user'],
        ]);
    }

    public function logout(Request $request, ApiTokenService $tokens): Response
    {
        $token = (string) $request->attribute('api_token', '');
        $tokens->revoke($token);
        return ApiResponse::success(['message' => 'Logout berhasil.']);
    }

    public function me(Request $request): Response
    {
        $user = $request->attribute('api_user');
        return ApiResponse::success([
            'id' => (int) ($user['user_id'] ?? 0),
            'name' => (string) ($user['name'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $payload = $request->all();
        if ($payload !== []) {
            return $payload;
        }
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }
}
