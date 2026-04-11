<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\SecurityLogger;
use System\Http\Request;
use System\Http\Response;

/**
 * AuditMiddleware — Phase 3, Layer 6
 *
 * Detects and logs forbidden access (403) after response.
 * Must run after Auth middleware so user_id is available.
 */
class AuditMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        /** @var Response $response */
        $response = $next($request);

        if ($response->statusCode() === 403) {
            SecurityLogger::logSecurityEvent(
                eventCode:       SecurityLogger::EVT_FORBIDDEN_ACCESS,
                category:        'authorization',
                severity:        'medium',
                riskScore:       SecurityLogger::RISK_MEDIUM,
                detectionSource: 'AuditMiddleware',
                context:         ['path' => $request->path(), 'method' => $request->method()],
                actionTaken:     'blocked',
            );
        }

        return $response;
    }
}
