<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\SecurityLogger;
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
            SecurityLogger::logSecurityEvent(
                eventCode:       SecurityLogger::EVT_CSRF_FAILED,
                category:        'system',
                severity:        'medium',
                riskScore:       SecurityLogger::RISK_MEDIUM,
                detectionSource: 'VerifyCsrfToken',
                context:         ['path' => $request->path(), 'method' => $request->method()],
                actionTaken:     'blocked',
            );
            return Response::html('CSRF token mismatch.', 403);
        }

        return $next($request);
    }
}
