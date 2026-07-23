<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\ApiResponse;
use System\Http\Request;

final class ApiRateLimit
{
    private const LIMIT = 60;
    private const WINDOW = 60;

    public function handle(Request $request, callable $next): mixed
    {
        $key = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . $request->path());
        $path = app()->basePath('storage/cache/api_rate_limit.json');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $data = is_array($data) ? $data : [];
        $now = time();
        $entry = is_array($data[$key] ?? null) ? $data[$key] : ['count' => 0, 'started_at' => $now];
        if ($now - (int) $entry['started_at'] >= self::WINDOW) {
            $entry = ['count' => 0, 'started_at' => $now];
        }
        $entry['count']++;
        $data[$key] = $entry;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, json_encode($data), LOCK_EX);
        if ((int) $entry['count'] > self::LIMIT) {
            return ApiResponse::error('RATE_LIMITED', 'Terlalu banyak request.', 429, ['retry_after' => self::WINDOW - ($now - (int) $entry['started_at'])]);
        }
        return $next($request);
    }
}
