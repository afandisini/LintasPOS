<?php

declare(strict_types=1);

namespace App\Middleware;

use System\Http\Request;
use System\Http\Response;

class Authenticate
{
    public function handle(Request $request, callable $next): mixed
    {
        $auth = $_SESSION['auth'] ?? null;
        if (!is_array($auth) || !isset($auth['id'])) {
            $_SESSION['_flash_auth'] = [
                'type' => 'error',
                'message' => 'Silakan login terlebih dahulu.',
            ];

            return Response::redirect('/login');
        }

        $currentIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $currentUaHash = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
        $storedIp = (string) ($auth['ip'] ?? '');
        $storedUaHash = (string) ($auth['ua_hash'] ?? '');

        // Bind authenticated session to original network + user-agent fingerprint.
        if ($storedIp !== '' && $storedUaHash !== '' && ($storedIp !== $currentIp || $storedUaHash !== $currentUaHash)) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_flash_auth'] = [
                'type' => 'error',
                'message' => 'Sesi tidak valid. Silakan login ulang.',
            ];
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
