<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\SecurityLogger;
use System\Http\Request;
use System\Http\Response;

class AuthController
{
    public function __construct(private AuthService $authService)
    {
    }

    public function root(Request $request): Response
    {
        $auth = $_SESSION['auth'] ?? null;
        if (is_array($auth) && isset($auth['id'])) {
            return Response::redirect('/dashboard');
        }

        return Response::redirect('/login');
    }

    public function showLogin(Request $request): Response
    {
        $flash = $this->takeFlash();

        $html = app()->view()->render('auth/login', [
            'title' => 'Login ' . brand_name(),
            'message' => (string) ($flash['message'] ?? ''),
            'messageType' => (string) ($flash['type'] ?? ''),
            'oldIdentity' => (string) ($flash['old_identity'] ?? ''),
        ]);

        return Response::html($html);
    }

    public function login(Request $request): Response
    {
        $identity = trim((string) $request->input('identity', ''));
        $password = (string) $request->input('password', '');
        $ipAddress = $this->clientIp();

        $result = $this->authService->attempt($identity, $password, $ipAddress);
        if (($result['ok'] ?? false) !== true) {
            $this->putFlash([
                'type' => 'error',
                'message' => (string) ($result['message'] ?? 'Login gagal.'),
                'old_identity' => $identity,
            ]);
            toast_add((string) ($result['message'] ?? 'Login gagal.'), 'error');

            SecurityLogger::logAuth(
                authEvent: SecurityLogger::AUTH_LOGIN_FAILED,
                result: 'failed',
                identifier: $identity,
                userId: null,
                riskScore: SecurityLogger::RISK_LOW,
            );

            return Response::redirect('/login');
        }

        $user = is_array($result['user'] ?? null) ? $result['user'] : [];

        session_regenerate_id(true);
        $_SESSION['auth'] = [
            'id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['name'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'avatar' => $user['avatar'] ?? null,
            'ip' => $ipAddress,
            'ua_hash' => hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')),
            'login_at' => date('Y-m-d H:i:s'),
        ];

        SecurityLogger::logAuth(
            authEvent: SecurityLogger::AUTH_LOGIN_SUCCESS,
            result: 'success',
            identifier: $identity,
            userId: (int) ($user['id'] ?? 0),
            riskScore: SecurityLogger::RISK_NORMAL,
        );

        toast_add('Login berhasil. Selamat datang, ' . (string) ($user['name'] ?? 'User') . '.', 'success');

        return Response::redirect('/dashboard');
    }

    public function logout(Request $request): Response
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $auth = $_SESSION['auth'] ?? [];
            $userId = is_array($auth) ? ((int) ($auth['id'] ?? 0) ?: null) : null;
            $username = is_array($auth) ? (string) ($auth['username'] ?? '') : '';

            SecurityLogger::logAuth(
                authEvent: SecurityLogger::AUTH_LOGOUT,
                result: 'success',
                identifier: $username,
                userId: $userId,
                riskScore: SecurityLogger::RISK_NORMAL,
            );

            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'] ?? '/',
                    $params['domain'] ?? '',
                    (bool) ($params['secure'] ?? false),
                    (bool) ($params['httponly'] ?? true)
                );
            }

            session_destroy();
            session_id(bin2hex(random_bytes(16)));
            session_start();
            session_regenerate_id(true);
            toast_add('Anda berhasil logout.', 'info');
        }

        return Response::redirect('/login');
    }

    private function putFlash(array $payload): void
    {
        $_SESSION['_flash_auth'] = $payload;
    }

    private function takeFlash(): array
    {
        $payload = $_SESSION['_flash_auth'] ?? [];
        unset($_SESSION['_flash_auth']);

        return is_array($payload) ? $payload : [];
    }

    private function clientIp(): string
    {
        // Gunakan REMOTE_ADDR langsung — tidak percaya X-Forwarded-For
        // karena bisa di-spoof untuk bypass throttle.
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        return $remote !== '' ? $remote : '127.0.0.1';
    }
}
