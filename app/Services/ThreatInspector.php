<?php

declare(strict_types=1);

namespace App\Services;

use System\Http\Request;

/**
 * ThreatInspector — Phase 2, Layer 3
 *
 * Inspects incoming request for known attack patterns.
 * Returns a ThreatResult with risk_score, findings, and recommended action.
 */
class ThreatInspector
{
    // Patterns: key = event_code, value = [regex, severity, risk_score]
    private const SQLI_PATTERNS = [
        '/(\bunion\b.{0,30}\bselect\b)/i',
        '/(\bselect\b.{0,60}\bfrom\b)/i',
        '/(\bdrop\b.{0,20}\btable\b)/i',
        '/(\binsert\b.{0,20}\binto\b)/i',
        '/(\bdelete\b.{0,20}\bfrom\b)/i',
        '/(\bexec\b|\bexecute\b).{0,20}\(/i',
        '/(sleep\s*\(|benchmark\s*\(|waitfor\s+delay)/i',
        '/(\bor\b|\band\b)\s+[\'"0-9].{0,10}[=<>]/i',
        "/(--|;|'\\s*or|'\\s*and|\"\\s*or|\"\\s*and)/i",
        '/(%27|%22|%3b|%2d%2d|0x[0-9a-f]{2,})/i',
    ];

    private const XSS_PATTERNS = [
        '/<script[\s>]/i',
        '/javascript\s*:/i',
        '/on(error|load|click|mouseover|focus|blur|change|submit|keyup|keydown)\s*=/i',
        '/(<|%3c)(iframe|object|embed|form|input|svg|img)[^>]*>/i',
        '/(alert|confirm|prompt)\s*\(/i',
        '/document\s*\.\s*(cookie|write|location)/i',
        '/(eval|fromcharcode|atob|btoa)\s*\(/i',
        '/expression\s*\(/i',
        '/vbscript\s*:/i',
    ];

    private const PATH_TRAVERSAL_PATTERNS = [
        '/\.\.[\/\\\\]/',
        '/%2e%2e[%2f%5c]/i',
        '/%252e%252e/i',
        '/\/etc\/(passwd|shadow|hosts|group)/i',
        '/\/proc\/self/i',
        '/\\\\windows\\\\system32/i',
        '/\.(htaccess|htpasswd|env|git|svn|DS_Store)/i',
    ];

    private const SUSPICIOUS_HEADERS = [
        'x-forwarded-host',
        'x-original-url',
        'x-rewrite-url',
        'x-custom-ip-authorization',
        'x-forwarded-server',
    ];

    // Paths that should never be probed by normal users
    private const SENSITIVE_PATHS = [
        '/wp-admin', '/wp-login', '/phpmyadmin', '/adminer',
        '/.env', '/.git', '/.svn', '/config.php', '/setup.php',
        '/install.php', '/xmlrpc.php', '/shell.php', '/cmd.php',
        '/eval.php', '/c99.php', '/r57.php',
    ];

    /**
     * Inspect a request and return threat findings.
     *
     * @return array{risk_score: int, is_suspicious: bool, findings: list<array{event_code: string, severity: string, risk_score: int, detail: string}>}
     */
    public static function inspect(Request $request): array
    {
        $findings = [];
        $totalRisk = 0;

        $allInput = self::flattenInput($request->all());
        $path     = strtolower($request->path());
        $qs       = strtolower((string) ($_SERVER['QUERY_STRING'] ?? ''));
        $ua       = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        // ── SQLi ─────────────────────────────────────────────────────────
        $sqliTarget = $allInput . ' ' . $qs;
        foreach (self::SQLI_PATTERNS as $pattern) {
            if (preg_match($pattern, $sqliTarget)) {
                $findings[] = [
                    'event_code' => SecurityLogger::EVT_SQLI_PATTERN,
                    'severity'   => 'high',
                    'risk_score' => 70,
                    'detail'     => 'SQLi pattern matched: ' . $pattern,
                ];
                $totalRisk = max($totalRisk, 70);
                break; // one finding per category
            }
        }

        // ── XSS ──────────────────────────────────────────────────────────
        $xssTarget = $allInput . ' ' . $qs;
        foreach (self::XSS_PATTERNS as $pattern) {
            if (preg_match($pattern, $xssTarget)) {
                $findings[] = [
                    'event_code' => SecurityLogger::EVT_XSS_PATTERN,
                    'severity'   => 'high',
                    'risk_score' => 65,
                    'detail'     => 'XSS pattern matched: ' . $pattern,
                ];
                $totalRisk = max($totalRisk, 65);
                break;
            }
        }

        // ── Path Traversal ────────────────────────────────────────────────
        $traversalTarget = $path . ' ' . $qs . ' ' . $allInput;
        foreach (self::PATH_TRAVERSAL_PATTERNS as $pattern) {
            if (preg_match($pattern, $traversalTarget)) {
                $findings[] = [
                    'event_code' => SecurityLogger::EVT_PATH_TRAVERSAL,
                    'severity'   => 'high',
                    'risk_score' => 65,
                    'detail'     => 'Path traversal pattern matched',
                ];
                $totalRisk = max($totalRisk, 65);
                break;
            }
        }

        // ── Sensitive file probe ──────────────────────────────────────────
        foreach (self::SENSITIVE_PATHS as $sensitivePath) {
            if (str_starts_with($path, $sensitivePath)) {
                $findings[] = [
                    'event_code' => 'GUEST_SENSITIVE_FILE_PROBE',
                    'severity'   => 'medium',
                    'risk_score' => 40,
                    'detail'     => 'Sensitive path probed: ' . $path,
                ];
                $totalRisk = max($totalRisk, 40);
                break;
            }
        }

        // ── Suspicious headers ────────────────────────────────────────────
        foreach (self::SUSPICIOUS_HEADERS as $header) {
            if ($request->header($header) !== null) {
                $findings[] = [
                    'event_code' => 'GUEST_SUSPICIOUS_HEADER',
                    'severity'   => 'medium',
                    'risk_score' => 35,
                    'detail'     => 'Suspicious header present: ' . $header,
                ];
                $totalRisk = max($totalRisk, 35);
                break;
            }
        }

        // ── Empty / bot-like User-Agent ───────────────────────────────────
        if ($ua === '' || strlen($ua) < 10) {
            $findings[] = [
                'event_code' => 'GUEST_SUSPICIOUS_UA',
                'severity'   => 'low',
                'risk_score' => 15,
                'detail'     => 'Empty or very short User-Agent',
            ];
            $totalRisk = max($totalRisk, 15);
        }

        return [
            'risk_score'   => min(100, $totalRisk),
            'is_suspicious' => $totalRisk >= 30,
            'findings'     => $findings,
        ];
    }

    /**
     * Flatten all input values into a single string for pattern matching.
     *
     * @param array<string,mixed> $input
     */
    private static function flattenInput(array $input): string
    {
        $parts = [];
        array_walk_recursive($input, static function (mixed $v) use (&$parts): void {
            if (is_string($v) || is_numeric($v)) {
                $parts[] = (string) $v;
            }
        });
        return implode(' ', $parts);
    }
}
