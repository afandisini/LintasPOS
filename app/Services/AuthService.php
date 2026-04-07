<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class AuthService
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 600;

    public function attempt(string $identity, string $password, string $ipAddress): array
    {
        $identity = trim($identity);
        $password = trim($password);

        if ($identity === '' || $password === '') {
            return [
                'ok' => false,
                'message' => 'Username/email dan password wajib diisi.',
                'user' => null,
            ];
        }

        $throttle = $this->readThrottle();
        $key = $this->throttleKey($identity, $ipAddress);
        $entry = $throttle[$key] ?? ['count' => 0, 'lock_until' => 0];
        $now = time();

        if (($entry['lock_until'] ?? 0) > $now) {
            $waitSeconds = (int) (($entry['lock_until'] ?? 0) - $now);
            return [
                'ok' => false,
                'message' => 'Terlalu banyak percobaan login. Coba lagi dalam ' . $waitSeconds . ' detik.',
                'user' => null,
            ];
        }

        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.user, u.pass, u.hak_akses_id AS akses, u.active, u.avatar, '
            . 'COALESCE(h.hak_akses, CAST(u.hak_akses_id AS CHAR)) AS role_label '
            . 'FROM users u '
            . 'LEFT JOIN hak_akses h ON h.id = u.hak_akses_id '
            . 'WHERE (u.user = :identity_user OR u.email = :identity_email) '
            . 'LIMIT 1'
        );
        $statement->execute([
            'identity_user' => $identity,
            'identity_email' => $identity,
        ]);
        $user = $statement->fetch();

        $passwordHash = is_array($user) ? (string) ($user['pass'] ?? '') : '';
        $verified = $passwordHash !== '' && password_verify($password, $passwordHash);

        if (!$verified || !is_array($user)) {
            $this->recordFailure($throttle, $key, $now);
            return [
                'ok' => false,
                'message' => 'Kredensial tidak valid.',
                'user' => null,
            ];
        }

        if ((string) ($user['active'] ?? '0') !== '1') {
            return [
                'ok' => false,
                'message' => 'Akun nonaktif. Hubungi administrator.',
                'user' => null,
            ];
        }

        unset($throttle[$key]);
        $this->writeThrottle($throttle);

        if (password_needs_rehash($passwordHash, PASSWORD_DEFAULT)) {
            $update = $pdo->prepare('UPDATE users SET pass = :pass WHERE id = :id');
            $update->execute([
                'pass' => password_hash($password, PASSWORD_DEFAULT),
                'id' => (int) $user['id'],
            ]);
        }

        return [
            'ok' => true,
            'message' => 'Login berhasil.',
            'user' => [
                'id' => (int) ($user['id'] ?? 0),
                'name' => (string) ($user['name'] ?? ''),
                'username' => (string) ($user['user'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'role' => (string) ($user['role_label'] ?? $user['akses'] ?? ''),
                'avatar' => $user['avatar'] ?? null,
            ],
        ];
    }

    private function recordFailure(array $throttle, string $key, int $now): void
    {
        $current = $throttle[$key] ?? ['count' => 0, 'lock_until' => 0];
        $count = (int) ($current['count'] ?? 0) + 1;
        $lockUntil = 0;

        if ($count >= self::MAX_ATTEMPTS) {
            $lockUntil = $now + self::LOCK_SECONDS;
            $count = 0;
        }

        $throttle[$key] = [
            'count' => $count,
            'lock_until' => $lockUntil,
        ];

        $this->writeThrottle($throttle);
    }

    private function throttleKey(string $identity, string $ipAddress): string
    {
        return hash('sha256', strtolower($identity) . '|' . $ipAddress);
    }

    private function throttlePath(): string
    {
        $path = app()->basePath('storage/cache/login_throttle.json');
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create throttle directory: ' . $directory);
        }

        return $path;
    }

    private function readThrottle(): array
    {
        $path = $this->throttlePath();
        if (!is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if (!is_string($content) || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeThrottle(array $data): void
    {
        $path = $this->throttlePath();
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            return;
        }

        file_put_contents($path, $encoded, LOCK_EX);
    }
}
