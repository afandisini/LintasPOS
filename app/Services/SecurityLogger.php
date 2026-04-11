<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

/**
 * SecurityLogger — Phase 1 Foundation
 *
 * Handles all security-related logging:
 *   - request_activity
 *   - security_events
 *   - auth_activity
 *   - admin_audit_logs
 */
class SecurityLogger
{
    // ── Risk score constants ──────────────────────────────────────────────
    public const RISK_NORMAL    = 0;
    public const RISK_LOW       = 15;
    public const RISK_MEDIUM    = 40;
    public const RISK_HIGH      = 70;
    public const RISK_CRITICAL  = 95;

    // ── Auth event codes ──────────────────────────────────────────────────
    public const AUTH_LOGIN_SUCCESS = 'AUTH_LOGIN_SUCCESS';
    public const AUTH_LOGIN_FAILED  = 'AUTH_LOGIN_FAILED';
    public const AUTH_LOGOUT        = 'AUTH_LOGOUT';
    public const AUTH_SESSION_INVALID = 'AUTH_SESSION_INVALID';

    // ── Security event codes ──────────────────────────────────────────────
    public const EVT_SQLI_PATTERN       = 'GUEST_SQLI_PATTERN';
    public const EVT_XSS_PATTERN        = 'GUEST_XSS_PATTERN';
    public const EVT_PATH_TRAVERSAL     = 'GUEST_PATH_TRAVERSAL';
    public const EVT_404_FLOOD          = 'GUEST_404_FLOOD';
    public const EVT_BRUTEFORCE_IP      = 'GUEST_BRUTEFORCE_IP';
    public const EVT_FORBIDDEN_ACCESS   = 'USER_FORBIDDEN_ROUTE_ACCESS';
    public const EVT_CSRF_FAILED        = 'SYSTEM_CSRF_FAILED';

    private static ?PDO $pdo = null;

    private static function db(): ?PDO
    {
        if (self::$pdo === null) {
            try {
                self::$pdo = Database::connection();
            } catch (Throwable) {
                return null;
            }
        }
        return self::$pdo;
    }

    // ── Request ID ───────────────────────────────────────────────────────

    public static function generateRequestId(): string
    {
        return sprintf(
            '%08x-%04x-4%03x-%04x-%012x',
            random_int(0, 0xFFFFFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0x0FFF),
            random_int(0x8000, 0xBFFF),
            random_int(0, 0xFFFFFFFFFFFF)
        );
    }

    public static function currentRequestId(): string
    {
        if (!isset($_SERVER['_SECURITY_REQUEST_ID'])) {
            $_SERVER['_SECURITY_REQUEST_ID'] = self::generateRequestId();
        }
        return (string) $_SERVER['_SECURITY_REQUEST_ID'];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    private static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
    }

    private static function currentUserId(): ?int
    {
        $auth = $_SESSION['auth'] ?? null;
        if (is_array($auth) && isset($auth['id']) && (int) $auth['id'] > 0) {
            return (int) $auth['id'];
        }
        return null;
    }

    private static function fingerprint(string $data): string
    {
        return $data !== '' ? hash('sha256', $data) : '';
    }

    private static function maskIdentifier(string $identifier): string
    {
        if (str_contains($identifier, '@')) {
            [$local, $domain] = explode('@', $identifier, 2);
            return substr($local, 0, 2) . '***@' . $domain;
        }
        $len = mb_strlen($identifier);
        if ($len <= 3) return str_repeat('*', $len);
        return mb_substr($identifier, 0, 2) . str_repeat('*', max(1, $len - 3)) . mb_substr($identifier, -1);
    }

    // ── 1. Request Activity ───────────────────────────────────────────────

    /**
     * Log a completed request. Call after response is ready.
     */
    public static function logRequest(
        string $method,
        string $path,
        int $statusCode,
        int $responseTimeMs,
        bool $isSuspicious = false,
        int $riskScore = 0,
        string $queryString = '',
        string $bodyFingerprint = ''
    ): void {
        $db = self::db();
        if ($db === null) return;

        try {
            $stmt = $db->prepare(
                'INSERT INTO request_activity
                 (request_id, occurred_at, user_id, ip_address, user_agent,
                  method, path, status_code, response_time_ms,
                  query_fingerprint, body_fingerprint, is_suspicious, risk_score)
                 VALUES
                 (:request_id, NOW(), :user_id, :ip, :ua,
                  :method, :path, :status, :rt,
                  :qfp, :bfp, :suspicious, :risk)'
            );
            $stmt->execute([
                'request_id' => self::currentRequestId(),
                'user_id'    => self::currentUserId(),
                'ip'         => self::clientIp(),
                'ua'         => self::userAgent(),
                'method'     => strtoupper($method),
                'path'       => substr($path, 0, 512),
                'status'     => $statusCode,
                'rt'         => $responseTimeMs,
                'qfp'        => self::fingerprint($queryString),
                'bfp'        => $bodyFingerprint !== '' ? $bodyFingerprint : '',
                'suspicious' => $isSuspicious ? 1 : 0,
                'risk'       => min(100, max(0, $riskScore)),
            ]);
        } catch (Throwable) {
            // Never crash the app due to logging failure
        }
    }

    // ── 2. Security Events ────────────────────────────────────────────────

    /**
     * Record a security event.
     *
     * @param array<string,mixed> $context
     */
    public static function logSecurityEvent(
        string $eventCode,
        string $category,
        string $severity,
        int $riskScore,
        string $detectionSource,
        array $context = [],
        string $actionTaken = 'logged'
    ): void {
        $db = self::db();
        if ($db === null) return;

        $userId = self::currentUserId();
        $actorType = $userId !== null ? 'user' : 'guest';
        $stage = $userId !== null ? 'after_login' : 'before_login';

        // Sanitize payload — never log raw sensitive values
        $safeContext = $context;
        foreach (['password', 'pass', 'token', 'secret', 'key'] as $sensitive) {
            if (isset($safeContext[$sensitive])) {
                $safeContext[$sensitive] = '[REDACTED]';
            }
        }

        try {
            $stmt = $db->prepare(
                'INSERT INTO security_events
                 (occurred_at, event_code, category, severity, risk_score,
                  actor_type, user_id, ip_address, path,
                  detection_stage, detection_source, payload_summary, action_taken, request_id)
                 VALUES
                 (NOW(), :code, :cat, :sev, :risk,
                  :actor, :uid, :ip, :path,
                  :stage, :source, :payload, :action, :rid)'
            );
            $stmt->execute([
                'code'    => $eventCode,
                'cat'     => $category,
                'sev'     => $severity,
                'risk'    => min(100, max(0, $riskScore)),
                'actor'   => $actorType,
                'uid'     => $userId,
                'ip'      => self::clientIp(),
                'path'    => substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 512),
                'stage'   => $stage,
                'source'  => $detectionSource,
                'payload' => !empty($safeContext) ? json_encode($safeContext, JSON_UNESCAPED_UNICODE) : null,
                'action'  => $actionTaken,
                'rid'     => self::currentRequestId(),
            ]);
        } catch (Throwable) {
        }
    }

    // ── 3. Auth Activity ──────────────────────────────────────────────────

    public static function logAuth(
        string $authEvent,
        string $result,
        string $identifier,
        ?int $userId = null,
        int $attemptCount = 1,
        int $riskScore = 0
    ): void {
        $db = self::db();
        if ($db === null) return;

        try {
            $stmt = $db->prepare(
                'INSERT INTO auth_activity
                 (occurred_at, auth_event, result, user_id, identifier_masked,
                  ip_address, user_agent_hash, session_hash, attempt_count, risk_score, request_id)
                 VALUES
                 (NOW(), :event, :result, :uid, :ident,
                  :ip, :ua_hash, :sess_hash, :attempts, :risk, :rid)'
            );
            $stmt->execute([
                'event'     => $authEvent,
                'result'    => $result,
                'uid'       => $userId,
                'ident'     => self::maskIdentifier($identifier),
                'ip'        => self::clientIp(),
                'ua_hash'   => self::fingerprint((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
                'sess_hash' => self::fingerprint(session_id() ?: ''),
                'attempts'  => $attemptCount,
                'risk'      => min(100, max(0, $riskScore)),
                'rid'       => self::currentRequestId(),
            ]);
        } catch (Throwable) {
        }
    }

    // ── 4. Admin Audit Log ────────────────────────────────────────────────

    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    public static function logAudit(
        string $moduleName,
        string $actionName,
        string $targetType,
        string $targetId,
        ?array $before = null,
        ?array $after = null,
        bool $isSensitive = false,
        int $riskScore = 0
    ): void {
        $db = self::db();
        if ($db === null) return;

        $userId = self::currentUserId();
        if ($userId === null) return;

        $diffSummary = self::buildDiffSummary($before, $after);

        // Redact sensitive fields from snapshots
        $sensitiveFields = ['pass', 'password', 'token', 'secret', 'key', 'api_key'];
        if ($before !== null) {
            foreach ($sensitiveFields as $f) {
                if (isset($before[$f])) $before[$f] = '[REDACTED]';
            }
        }
        if ($after !== null) {
            foreach ($sensitiveFields as $f) {
                if (isset($after[$f])) $after[$f] = '[REDACTED]';
            }
        }

        try {
            $stmt = $db->prepare(
                'INSERT INTO admin_audit_logs
                 (occurred_at, user_id, module_name, action_name, target_type, target_id,
                  before_snapshot, after_snapshot, diff_summary, is_sensitive, risk_score, ip_address, request_id)
                 VALUES
                 (NOW(), :uid, :module, :action, :ttype, :tid,
                  :before, :after, :diff, :sensitive, :risk, :ip, :rid)'
            );
            $stmt->execute([
                'uid'       => $userId,
                'module'    => $moduleName,
                'action'    => strtoupper($actionName),
                'ttype'     => $targetType,
                'tid'       => $targetId,
                'before'    => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                'after'     => $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
                'diff'      => $diffSummary,
                'sensitive' => $isSensitive ? 1 : 0,
                'risk'      => min(100, max(0, $riskScore)),
                'ip'        => self::clientIp(),
                'rid'       => self::currentRequestId(),
            ]);
        } catch (Throwable) {
        }
    }

    // ── Diff helper ───────────────────────────────────────────────────────

    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    private static function buildDiffSummary(?array $before, ?array $after): ?string
    {
        if ($before === null && $after === null) return null;
        if ($before === null) return 'Created';
        if ($after === null) return 'Deleted';

        $changed = [];
        $allKeys = array_unique(array_merge(array_keys($before), array_keys($after)));
        foreach ($allKeys as $key) {
            $bVal = $before[$key] ?? null;
            $aVal = $after[$key] ?? null;
            if ($bVal !== $aVal) {
                $changed[] = $key;
            }
        }

        return $changed !== [] ? 'Changed: ' . implode(', ', $changed) : 'No changes';
    }
}
