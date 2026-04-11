<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

/**
 * BlockerService — Phase 4, Response
 *
 * Manages security_blocks table:
 *  - blockIp()     — block an IP address
 *  - blockUser()   — block a user account
 *  - blockSession()— block a session hash
 *  - isIpBlocked() — check if IP is currently blocked
 *  - isUserBlocked()
 *  - unblock()     — manually unblock by id
 *  - revokeUserSessions() — destroy all active sessions for a user
 */
class BlockerService
{
    // Default block durations (seconds)
    public const BLOCK_SHORT  = 600;    // 10 min — brute force
    public const BLOCK_MEDIUM = 3600;   // 1 hour — repeated attacks
    public const BLOCK_LONG   = 86400;  // 24 hours — critical threats
    public const BLOCK_PERM   = 0;      // permanent (expires_at = NULL)

    private static function db(): ?PDO
    {
        try {
            return Database::connection();
        } catch (Throwable) {
            return null;
        }
    }

    // ── Block IP ─────────────────────────────────────────────────────────────

    public static function blockIp(
        string $ip,
        string $reasonCode,
        string $severity = 'medium',
        int $durationSeconds = self::BLOCK_SHORT,
        string $blockedBy = 'system',
        string $notes = ''
    ): bool {
        $db = self::db();
        if ($db === null || $ip === '') return false;

        try {
            // Deactivate existing active blocks for same IP
            $db->prepare(
                "UPDATE security_blocks SET is_active = 0, unblocked_at = NOW()
                 WHERE block_type = 'ip' AND block_value = :ip AND is_active = 1"
            )->execute(['ip' => $ip]);

            $expiresExpr = $durationSeconds > 0
                ? 'DATE_ADD(NOW(), INTERVAL ' . $durationSeconds . ' SECOND)'
                : 'NULL';

            $db->prepare(
                "INSERT INTO security_blocks
                 (block_type, block_value, reason_code, severity, expires_at, is_active, blocked_by, notes, created_at)
                 VALUES ('ip', :ip, :reason, :severity, {$expiresExpr}, 1, :by, :notes, NOW())"
            )->execute([
                'ip'       => $ip,
                'reason'   => $reasonCode,
                'severity' => $severity,
                'by'       => $blockedBy,
                'notes'    => $notes !== '' ? $notes : null,
            ]);

            SecurityLogger::logSecurityEvent(
                eventCode:       'SYSTEM_IP_BLOCKED',
                category:        'response',
                severity:        $severity,
                riskScore:       self::severityToRisk($severity),
                detectionSource: 'BlockerService',
                context:         ['ip' => $ip, 'reason' => $reasonCode, 'duration' => $durationSeconds],
                actionTaken:     'blocked',
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    // ── Block User ────────────────────────────────────────────────────────────

    public static function blockUser(
        int $userId,
        string $reasonCode,
        string $severity = 'high',
        int $durationSeconds = self::BLOCK_LONG,
        string $blockedBy = 'system'
    ): bool {
        $db = self::db();
        if ($db === null || $userId <= 0) return false;

        $expiresExpr = $durationSeconds > 0
            ? 'DATE_ADD(NOW(), INTERVAL ' . $durationSeconds . ' SECOND)'
            : 'NULL';

        try {
            $db->prepare(
                "UPDATE security_blocks SET is_active = 0, unblocked_at = NOW()
                 WHERE block_type = 'user' AND block_value = :uid AND is_active = 1"
            )->execute(['uid' => (string) $userId]);

            $db->prepare(
                "INSERT INTO security_blocks
                 (block_type, block_value, reason_code, severity, expires_at, is_active, blocked_by, created_at)
                 VALUES ('user', :uid, :reason, :severity, {$expiresExpr}, 1, :by, NOW())"
            )->execute([
                'uid'      => (string) $userId,
                'reason'   => $reasonCode,
                'severity' => $severity,
                'by'       => $blockedBy,
            ]);

            // Revoke all active sessions for this user
            self::revokeUserSessions($userId);

            SecurityLogger::logSecurityEvent(
                eventCode:       'SYSTEM_USER_BLOCKED',
                category:        'response',
                severity:        $severity,
                riskScore:       self::severityToRisk($severity),
                detectionSource: 'BlockerService',
                context:         ['user_id' => $userId, 'reason' => $reasonCode],
                actionTaken:     'blocked',
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    // ── Block Session ─────────────────────────────────────────────────────────

    public static function blockSession(
        string $sessionHash,
        string $reasonCode,
        string $severity = 'high',
        string $blockedBy = 'system'
    ): bool {
        $db = self::db();
        if ($db === null || $sessionHash === '') return false;

        try {
            $db->prepare(
                "INSERT INTO security_blocks
                 (block_type, block_value, reason_code, severity, expires_at, is_active, blocked_by, created_at)
                 VALUES ('session', :hash, :reason, :severity, NULL, 1, :by, NOW())"
            )->execute([
                'hash'     => $sessionHash,
                'reason'   => $reasonCode,
                'severity' => $severity,
                'by'       => $blockedBy,
            ]);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    // ── Check Blocks ──────────────────────────────────────────────────────────

    public static function isIpBlocked(string $ip): bool
    {
        return self::isBlocked('ip', $ip);
    }

    public static function isUserBlocked(int $userId): bool
    {
        return self::isBlocked('user', (string) $userId);
    }

    public static function isSessionBlocked(string $sessionHash): bool
    {
        return self::isBlocked('session', $sessionHash);
    }

    private static function isBlocked(string $type, string $value): bool
    {
        $db = self::db();
        if ($db === null) return false;

        try {
            $stmt = $db->prepare(
                "SELECT id FROM security_blocks
                 WHERE block_type = :type AND block_value = :value AND is_active = 1
                   AND (expires_at IS NULL OR expires_at > NOW())
                 LIMIT 1"
            );
            $stmt->execute(['type' => $type, 'value' => $value]);
            return $stmt->fetch() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    // ── Unblock ───────────────────────────────────────────────────────────────

    public static function unblockIp(string $ip): bool
    {
        return self::unblockByTypeValue('ip', $ip);
    }

    public static function unblockUser(int $userId): bool
    {
        return self::unblockByTypeValue('user', (string) $userId);
    }

    private static function unblockByTypeValue(string $type, string $value): bool
    {
        $db = self::db();
        if ($db === null) return false;

        try {
            $db->prepare(
                "UPDATE security_blocks SET is_active = 0, unblocked_at = NOW()
                 WHERE block_type = :type AND block_value = :value AND is_active = 1"
            )->execute(['type' => $type, 'value' => $value]);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    // ── Session Revoke ────────────────────────────────────────────────────────

    /**
     * Revoke all file-based sessions for a user by scanning session files.
     * Works with PHP file-based session storage.
     */
    public static function revokeUserSessions(int $userId): int
    {
        if ($userId <= 0) return 0;

        $sessionPath = (string) (ini_get('session.save_path') ?: sys_get_temp_dir());

        // Also check app storage/sessions
        $appSessionPath = defined('BASE_PATH')
            ? BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions'
            : '';

        $revoked = 0;
        foreach (array_filter([$sessionPath, $appSessionPath]) as $dir) {
            $revoked += self::revokeSessionsInDir($dir, $userId);
        }

        return $revoked;
    }

    private static function revokeSessionsInDir(string $dir, int $userId): int
    {
        if (!is_dir($dir)) return 0;

        $revoked = 0;
        $files = glob($dir . DIRECTORY_SEPARATOR . 'sess_*') ?: [];
        foreach ($files as $file) {
            if (!is_file($file)) continue;
            try {
                $content = (string) file_get_contents($file);
                // Check if this session belongs to the user
                if (str_contains($content, '"id";i:' . $userId . ';')
                    || str_contains($content, '"id";i:' . $userId . '}')
                    || str_contains($content, 's:2:"id";i:' . $userId)
                ) {
                    @unlink($file);
                    $revoked++;
                }
            } catch (Throwable) {
            }
        }
        return $revoked;
    }

    // ── Auto-block from RateLimit ─────────────────────────────────────────────

    /**
     * Called by RateLimitDetector when brute force threshold is hit.
     */
    public static function autoBlockFromBruteForce(string $ip, int $count): void
    {
        self::blockIp(
            ip:              $ip,
            reasonCode:      'GUEST_BRUTEFORCE_IP',
            severity:        'high',
            durationSeconds: self::BLOCK_SHORT,
            blockedBy:       'RateLimitDetector',
            notes:           "Auto-blocked after {$count} failed login attempts"
        );
    }

    /**
     * Called when request spam threshold is hit.
     */
    public static function autoBlockFromSpam(string $ip, int $count): void
    {
        self::blockIp(
            ip:              $ip,
            reasonCode:      'GUEST_REQUEST_SPAM',
            severity:        'medium',
            durationSeconds: self::BLOCK_SHORT,
            blockedBy:       'RateLimitMiddleware',
            notes:           "Auto-blocked after {$count} spam requests"
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function severityToRisk(string $severity): int
    {
        return match ($severity) {
            'critical' => SecurityLogger::RISK_CRITICAL,
            'high'     => SecurityLogger::RISK_HIGH,
            'medium'   => SecurityLogger::RISK_MEDIUM,
            default    => SecurityLogger::RISK_LOW,
        };
    }
}
