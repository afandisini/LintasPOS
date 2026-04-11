<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * RateLimitDetector — Phase 2, Layer 4
 *
 * File-based sliding window rate limiter.
 * No Redis/Memcached required — uses storage/cache/ratelimit/.
 *
 * Counters are keyed by: {type}_{ip}
 * Each counter file stores: [hits: [[timestamp,...]], blocked_until: int]
 */
class RateLimitDetector
{
    private static string $cacheDir = '';

    private static function dir(): string
    {
        if (self::$cacheDir === '') {
            $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
            self::$cacheDir = $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'ratelimit';
            if (!is_dir(self::$cacheDir)) {
                mkdir(self::$cacheDir, 0775, true);
            }
        }
        return self::$cacheDir;
    }

    private static function cacheFile(string $key): string
    {
        return self::dir() . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    }

    /**
     * @return array{hits: list<int>, blocked_until: int}
     */
    private static function read(string $key): array
    {
        $file = self::cacheFile($key);
        if (!is_file($file)) {
            return ['hits' => [], 'blocked_until' => 0];
        }
        try {
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data)) return ['hits' => [], 'blocked_until' => 0];
            return [
                'hits'          => is_array($data['hits'] ?? null) ? $data['hits'] : [],
                'blocked_until' => (int) ($data['blocked_until'] ?? 0),
            ];
        } catch (Throwable) {
            return ['hits' => [], 'blocked_until' => 0];
        }
    }

    private static function write(string $key, array $data): void
    {
        try {
            file_put_contents(self::cacheFile($key), json_encode($data), LOCK_EX);
        } catch (Throwable) {
        }
    }

    /**
     * Record a hit and check if threshold is exceeded.
     *
     * @return array{exceeded: bool, count: int, blocked: bool}
     */
    public static function hit(string $type, string $ip, int $threshold, int $windowSeconds, int $blockSeconds = 0): array
    {
        $key  = $type . '_' . $ip;
        $now  = time();
        $data = self::read($key);

        // Already blocked?
        if ($data['blocked_until'] > $now) {
            return ['exceeded' => true, 'count' => $threshold, 'blocked' => true];
        }

        // Slide window — remove hits older than window
        $cutoff = $now - $windowSeconds;
        $hits   = array_values(array_filter($data['hits'], fn(int $t) => $t >= $cutoff));

        // Add current hit
        $hits[] = $now;
        $count  = count($hits);

        $blockedUntil = $data['blocked_until'];
        $exceeded     = $count >= $threshold;

        if ($exceeded && $blockSeconds > 0) {
            $blockedUntil = $now + $blockSeconds;
        }

        self::write($key, ['hits' => $hits, 'blocked_until' => $blockedUntil]);

        return ['exceeded' => $exceeded, 'count' => $count, 'blocked' => $blockedUntil > $now];
    }

    /**
     * Check if an IP is currently blocked (without recording a hit).
     */
    public static function isBlocked(string $type, string $ip): bool
    {
        $data = self::read($type . '_' . $ip);
        return $data['blocked_until'] > time();
    }

    /**
     * Reset counter for an IP (e.g. after successful login).
     */
    public static function reset(string $type, string $ip): void
    {
        $file = self::cacheFile($type . '_' . $ip);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Check login brute force: 5 failures / 300s → block 600s.
     *
     * @return array{exceeded: bool, count: int, blocked: bool}
     */
    public static function checkLoginBruteForce(string $ip): array
    {
        $result = self::hit('login_fail', $ip, threshold: 5, windowSeconds: 300, blockSeconds: 600);
        if ($result['blocked'] && $result['count'] >= 5) {
            // Auto-block IP in security_blocks table
            try {
                \App\Services\BlockerService::autoBlockFromBruteForce($ip, $result['count']);
            } catch (\Throwable) {
            }
        }
        return $result;
    }

    /**
     * Check 404 flood: 10 hits / 60s.
     *
     * @return array{exceeded: bool, count: int, blocked: bool}
     */
    public static function check404Flood(string $ip): array
    {
        return self::hit('flood_404', $ip, threshold: 10, windowSeconds: 60, blockSeconds: 120);
    }

    /**
     * Check route scanning: 20 distinct 404s / 60s.
     *
     * @return array{exceeded: bool, count: int, blocked: bool}
     */
    public static function checkRouteScan(string $ip): array
    {
        return self::hit('route_scan', $ip, threshold: 20, windowSeconds: 60, blockSeconds: 300);
    }

    /**
     * Check general request spam: 120 req / 60s.
     *
     * @return array{exceeded: bool, count: int, blocked: bool}
     */
    public static function checkRequestSpam(string $ip): array
    {
        return self::hit('req_spam', $ip, threshold: 120, windowSeconds: 60, blockSeconds: 60);
    }
}
