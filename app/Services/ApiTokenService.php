<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class ApiTokenService
{
    public function issue(int $userId, ?string $name = null): array
    {
        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO api_tokens (user_id, name, token_hash, expires_at, created_at, last_used_at)
             VALUES (:user_id, :name, :token_hash, :expires_at, :created_at, NULL)'
        );
        $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);
        $stmt->execute([
            'user_id' => $userId,
            'name' => $name !== null && trim($name) !== '' ? trim($name) : 'api-client',
            'token_hash' => $hash,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['token' => $plain, 'expires_at' => $expiresAt];
    }

    public function find(string $plainToken): ?array
    {
        $hash = hash('sha256', $plainToken);
        $stmt = Database::connection()->prepare(
            'SELECT t.id AS token_id, t.user_id, t.expires_at, u.name, u.email, u.user AS username,
                    u.hak_akses_id, u.active, COALESCE(h.hak_akses, CAST(u.hak_akses_id AS CHAR)) AS role
             FROM api_tokens t
             INNER JOIN users u ON u.id = t.user_id
             LEFT JOIN hak_akses h ON h.id = u.hak_akses_id
             WHERE t.token_hash = :token_hash AND t.revoked_at IS NULL
               AND (t.expires_at IS NULL OR t.expires_at > CURRENT_TIMESTAMP)
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || (string) ($row['active'] ?? '0') !== '1') {
            return null;
        }

        Database::connection()->prepare('UPDATE api_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute(['id' => (int) $row['token_id']]);
        return $row;
    }

    public function revoke(string $plainToken): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP
             WHERE token_hash = :token_hash AND revoked_at IS NULL'
        );
        $stmt->execute(['token_hash' => hash('sha256', $plainToken)]);
        return $stmt->rowCount() > 0;
    }
}
