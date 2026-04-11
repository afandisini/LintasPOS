<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AlertService;
use App\Services\BlockerService;
use App\Services\RateLimitDetector;
use App\Services\SecurityLogger;
use System\Http\Request;
use System\Http\Response;

/**
 * RateLimitMiddleware — Phase 2, Layer 4
 *
 * Checks rate limits per IP:
 *  - General request spam
 *  - 404 flood (checked after response in RequestActivityMiddleware)
 *  - Route scan
 *
 * Login brute force is handled separately in AuthController / AuthService.
 */
class RateLimitMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        // ── General spam check ────────────────────────────────────────────
        $spam = RateLimitDetector::checkRequestSpam($ip);
        if ($spam['blocked']) {
            BlockerService::autoBlockFromSpam($ip, $spam['count']);
            SecurityLogger::logSecurityEvent(
                eventCode:       'GUEST_REQUEST_SPAM',
                category:        'ratelimit',
                severity:        'medium',
                riskScore:       SecurityLogger::RISK_MEDIUM,
                detectionSource: 'RateLimitMiddleware',
                context:         ['ip' => $ip, 'count' => $spam['count']],
                actionTaken:     'throttled',
            );
            return Response::html('Too Many Requests', 429);
        }

        /** @var Response $response */
        $response = $next($request);

        $statusCode = $response->statusCode();

        // ── 404 flood detection (post-response) ───────────────────────────
        if ($statusCode === 404) {
            $flood = RateLimitDetector::check404Flood($ip);
            if ($flood['exceeded']) {
                SecurityLogger::logSecurityEvent(
                    eventCode:       SecurityLogger::EVT_404_FLOOD,
                    category:        'recon',
                    severity:        'medium',
                    riskScore:       SecurityLogger::RISK_MEDIUM,
                    detectionSource: 'RateLimitMiddleware',
                    context:         ['ip' => $ip, 'count' => $flood['count']],
                    actionTaken:     $flood['blocked'] ? 'throttled' : 'logged',
                );
            }

            // Route scan: 404 flood also increments route scan counter
            $scan = RateLimitDetector::checkRouteScan($ip);
            if ($scan['exceeded']) {
                SecurityLogger::logSecurityEvent(
                    eventCode:       SecurityLogger::EVT_404_FLOOD,
                    category:        'recon',
                    severity:        'medium',
                    riskScore:       SecurityLogger::RISK_MEDIUM,
                    detectionSource: 'RateLimitMiddleware',
                    context:         ['ip' => $ip, 'scan_count' => $scan['count']],
                    actionTaken:     $scan['blocked'] ? 'throttled' : 'logged',
                );
            }
        }

        return $response;
    }
}
