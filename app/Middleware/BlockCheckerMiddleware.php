<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\BlockerService;
use App\Services\SecurityLogger;
use System\Http\Request;
use System\Http\Response;

/**
 * BlockCheckerMiddleware — Phase 4, Layer 2
 *
 * Checks if the current IP, user, or session is blocked.
 * Must run AFTER RequestActivityMiddleware (request_id exists)
 * but BEFORE any business logic.
 */
class BlockCheckerMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        // ── [1] IP block check ────────────────────────────────────────────
        if (BlockerService::isIpBlocked($ip)) {
            SecurityLogger::logSecurityEvent(
                eventCode:       'SYSTEM_BLOCKED_REQUEST',
                category:        'response',
                severity:        'high',
                riskScore:       SecurityLogger::RISK_HIGH,
                detectionSource: 'BlockCheckerMiddleware',
                context:         ['ip' => $ip, 'reason' => 'ip_blocked'],
                actionTaken:     'blocked',
            );
            return Response::html('Access Denied', 403);
        }

        // ── [2] User block check (only if authenticated) ──────────────────
        $auth = $_SESSION['auth'] ?? null;
        if (is_array($auth) && isset($auth['id'])) {
            $userId = (int) ($auth['id'] ?? 0);
            if ($userId > 0 && BlockerService::isUserBlocked($userId)) {
                SecurityLogger::logSecurityEvent(
                    eventCode:       'SYSTEM_BLOCKED_REQUEST',
                    category:        'response',
                    severity:        'high',
                    riskScore:       SecurityLogger::RISK_HIGH,
                    detectionSource: 'BlockCheckerMiddleware',
                    context:         ['user_id' => $userId, 'reason' => 'user_blocked'],
                    actionTaken:     'blocked',
                );
                // Destroy session and redirect to login
                $_SESSION = [];
                session_regenerate_id(true);
                return Response::redirect('/login');
            }

            // ── [3] Session block check ───────────────────────────────────
            $sessionHash = hash('sha256', session_id() ?: '');
            if (BlockerService::isSessionBlocked($sessionHash)) {
                SecurityLogger::logSecurityEvent(
                    eventCode:       'SYSTEM_BLOCKED_REQUEST',
                    category:        'response',
                    severity:        'high',
                    riskScore:       SecurityLogger::RISK_HIGH,
                    detectionSource: 'BlockCheckerMiddleware',
                    context:         ['reason' => 'session_blocked'],
                    actionTaken:     'session_destroyed',
                );
                $_SESSION = [];
                session_regenerate_id(true);
                return Response::redirect('/login');
            }
        }

        return $next($request);
    }
}
