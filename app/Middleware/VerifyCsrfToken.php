<?php

declare(strict_types=1);

namespace App\Middleware;

use System\Http\Request;
use System\Http\Response;
use System\Security\Csrf;

class VerifyCsrfToken
{
    public function handle(Request $request, callable $next): mixed
    {
        if (!(bool) config('security.csrf_enabled', true)) {
            return $next($request);
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            Csrf::token();
            return $next($request);
        }

        if (!Csrf::verify($request)) {
            return Response::html('CSRF token mismatch.', 403);
        }

        return $next($request);
    }
}
