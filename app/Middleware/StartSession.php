<?php

declare(strict_types=1);

namespace App\Middleware;

use System\Http\Request;

class StartSession
{
    public function handle(Request $request, callable $next): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $storage = app()->basePath((string) config('paths.storage', 'storage')) . DIRECTORY_SEPARATOR . 'sessions';
            if (!is_dir($storage)) {
                mkdir($storage, 0775, true);
            }

            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', (string) config('session.samesite', 'Lax'));

            session_name((string) config('session.cookie', 'aiticoreflex_session'));
            session_save_path($storage);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => (bool) config('session.secure', false) || $request->isSecure(),
                'httponly' => true,
                'samesite' => (string) config('session.samesite', 'Lax'),
            ]);
            session_start();
        }

        return $next($request);
    }
}
