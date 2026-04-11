<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\SecurityLogger;
use System\Http\Request;
use System\Http\Response;

/**
 * RequestActivityMiddleware — Phase 1, Layer 1 & 9
 *
 * - Generates request_id at the start of every request
 * - Measures response time
 * - Persists to request_activity after response is ready
 *
 * Register as the FIRST middleware in the global stack (bootstrap/app.php).
 */
class RequestActivityMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        // [1] Generate request_id & start timer
        $requestId = SecurityLogger::generateRequestId();
        $_SERVER['_SECURITY_REQUEST_ID'] = $requestId;
        $startTime = hrtime(true);

        /** @var Response $response */
        $response = $next($request);

        // [9] Record response — pick up risk score set by RequestInspectionMiddleware
        $responseTimeMs = (int) round((hrtime(true) - $startTime) / 1_000_000);
        $statusCode     = $response->statusCode();
        $riskScore      = (int) ($_SERVER['_SECURITY_RISK_SCORE'] ?? 0);
        $isSuspicious   = (bool) ($_SERVER['_SECURITY_IS_SUSPICIOUS'] ?? false);
        $queryString    = (string) ($_SERVER['QUERY_STRING'] ?? '');

        SecurityLogger::logRequest(
            method:          $request->method(),
            path:            $request->path(),
            statusCode:      $statusCode,
            responseTimeMs:  $responseTimeMs,
            isSuspicious:    $isSuspicious,
            riskScore:       $riskScore,
            queryString:     $queryString,
        );

        return $response;
    }
}
