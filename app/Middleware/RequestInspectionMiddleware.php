<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AlertService;
use App\Services\SecurityLogger;
use App\Services\ThreatInspector;
use System\Http\Request;
use System\Http\Response;

/**
 * RequestInspectionMiddleware — Phase 2, Layer 3
 *
 * Runs ThreatInspector on every request.
 * Stores risk_score + findings in $_SERVER so RequestActivityMiddleware
 * can persist them to request_activity.
 */
class RequestInspectionMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        $result = ThreatInspector::inspect($request);

        // Propagate to RequestActivityMiddleware via $_SERVER
        $_SERVER['_SECURITY_RISK_SCORE']    = $result['risk_score'];
        $_SERVER['_SECURITY_IS_SUSPICIOUS'] = $result['is_suspicious'] ? 1 : 0;

        // Log each finding as a security_event
        foreach ($result['findings'] as $finding) {
            SecurityLogger::logSecurityEvent(
                eventCode:       $finding['event_code'],
                category:        self::categoryFromCode($finding['event_code']),
                severity:        $finding['severity'],
                riskScore:       $finding['risk_score'],
                detectionSource: 'RequestInspectionMiddleware',
                context:         ['detail' => $finding['detail'], 'path' => $request->path()],
                actionTaken:     'logged',
            );
        }

        // Block request if critical threat detected (risk >= 90)
        if ($result['risk_score'] >= 90) {
            SecurityLogger::logSecurityEvent(
                eventCode:       'SYSTEM_REQUEST_BLOCKED',
                category:        'system',
                severity:        'critical',
                riskScore:       $result['risk_score'],
                detectionSource: 'RequestInspectionMiddleware',
                context:         ['path' => $request->path(), 'method' => $request->method()],
                actionTaken:     'blocked',
            );
            AlertService::critical(
                'SYSTEM_REQUEST_BLOCKED',
                'Critical threat blocked',
                ['path' => $request->path(), 'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '-'), 'risk' => $result['risk_score']]
            );
            return Response::html('Bad Request', 400);
        }

        return $next($request);
    }

    private static function categoryFromCode(string $code): string
    {
        return match (true) {
            str_contains($code, 'SQLI') || str_contains($code, 'XSS') => 'injection',
            str_contains($code, 'TRAVERSAL')                           => 'traversal',
            str_contains($code, 'PROBE') || str_contains($code, 'SCAN') => 'recon',
            str_contains($code, 'HEADER') || str_contains($code, 'UA')  => 'anomaly',
            default                                                      => 'general',
        };
    }
}
