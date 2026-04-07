<?php

declare(strict_types=1);

namespace App\Middleware;

use System\Http\Request;
use System\Http\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, callable $next): mixed
    {
        $auth = $_SESSION['auth'] ?? null;
        if (is_array($auth) && isset($auth['id'])) {
            return Response::redirect('/dashboard');
        }

        return $next($request);
    }
}
