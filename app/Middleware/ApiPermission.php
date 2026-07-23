<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\ApiResponse;
use App\Services\Database;
use System\Http\Request;

final class ApiPermission
{
    public function handle(Request $request, callable $next): mixed
    {
        $user = $request->attribute('api_user');
        $permission = $this->permissionFor($request->path());
        if ($permission === null || !is_array($user)) {
            return $next($request);
        }

        if ((int) ($user['user_id'] ?? 0) === 1 || in_array(strtolower((string) ($user['role'] ?? '')), ['administrator', 'admin', 'owner'], true)) {
            return $next($request);
        }

        $stmt = Database::connection()->prepare(
            'SELECT can_access FROM user_fitur_akses WHERE user_id = :user_id AND fitur_key = :feature LIMIT 1'
        );
        $stmt->execute(['user_id' => (int) ($user['user_id'] ?? 0), 'feature' => $permission]);
        if ((int) $stmt->fetchColumn() !== 1) {
            return ApiResponse::error('FORBIDDEN', 'Anda tidak memiliki izin untuk resource ini.', 403);
        }

        return $next($request);
    }

    private function permissionFor(string $path): ?string
    {
        return match (true) {
            str_ends_with($path, '/auth/me'), str_ends_with($path, '/auth/logout'), str_ends_with($path, '/health') => null,
            default => null,
        };
    }
}
