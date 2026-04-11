<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * AlertService — Phase 4, Response
 *
 * Sends alerts for critical security events.
 * Currently supports:
 *  - File-based alert log (storage/cache/security_alerts.log)
 *  - security_events table (via SecurityLogger)
 *
 * Extend this class to add email/webhook/Slack notifications.
 */
class AlertService
{
    private static string $logFile = '';

    private static function logPath(): string
    {
        if (self::$logFile === '') {
            $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
            $dir  = $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            self::$logFile = $dir . DIRECTORY_SEPARATOR . 'security_alerts.log';
        }
        return self::$logFile;
    }

    /**
     * Fire an alert for a critical security event.
     *
     * @param array<string,mixed> $context
     */
    public static function critical(string $eventCode, string $message, array $context = []): void
    {
        self::write('CRITICAL', $eventCode, $message, $context);

        SecurityLogger::logSecurityEvent(
            eventCode:       $eventCode,
            category:        'alert',
            severity:        'critical',
            riskScore:       SecurityLogger::RISK_CRITICAL,
            detectionSource: 'AlertService',
            context:         array_merge(['message' => $message], $context),
            actionTaken:     'alerted',
        );
    }

    /**
     * Fire a high-severity alert.
     *
     * @param array<string,mixed> $context
     */
    public static function high(string $eventCode, string $message, array $context = []): void
    {
        self::write('HIGH', $eventCode, $message, $context);

        SecurityLogger::logSecurityEvent(
            eventCode:       $eventCode,
            category:        'alert',
            severity:        'high',
            riskScore:       SecurityLogger::RISK_HIGH,
            detectionSource: 'AlertService',
            context:         array_merge(['message' => $message], $context),
            actionTaken:     'alerted',
        );
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function write(string $level, string $eventCode, string $message, array $context): void
    {
        try {
            $ip     = (string) ($_SERVER['REMOTE_ADDR'] ?? '-');
            $userId = (int) (($_SESSION['auth']['id'] ?? 0));
            $line   = sprintf(
                "[%s] [%s] [%s] ip=%s user_id=%d msg=%s ctx=%s\n",
                date('Y-m-d H:i:s'),
                $level,
                $eventCode,
                $ip,
                $userId,
                $message,
                json_encode($context, JSON_UNESCAPED_UNICODE)
            );
            file_put_contents(self::logPath(), $line, FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
        }
    }

    /**
     * Read last N lines from alert log.
     *
     * @return list<string>
     */
    public static function tail(int $lines = 50): array
    {
        $path = self::logPath();
        if (!is_file($path)) return [];

        try {
            $content = (string) file_get_contents($path);
            $all = array_filter(explode("\n", $content));
            return array_values(array_slice(array_values($all), -$lines));
        } catch (Throwable) {
            return [];
        }
    }
}
