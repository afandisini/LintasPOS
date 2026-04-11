<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use System\Http\Request;
use System\Http\Response;
use Throwable;

class HakAksesController
{
    /** GET /users/{id}/hak-akses — return JSON fitur + permission user */
    public function show(Request $request, string $id): Response
    {
        $userId = (int) $id;
        if ($userId <= 0) {
            return Response::json(['error' => 'Invalid user'], 400);
        }

        try {
            $pdo = Database::connection();

            // Cek apakah user adalah administrator (id=1 atau role administrator)
            $userStmt = $pdo->prepare(
                'SELECT u.id, LOWER(COALESCE(h.hak_akses, \'\')) AS role
                 FROM users u LEFT JOIN hak_akses h ON h.id = u.hak_akses_id
                 WHERE u.id = :id LIMIT 1'
            );
            $userStmt->execute([':id' => $userId]);
            $user = $userStmt->fetch();
            if (!is_array($user)) {
                return Response::json(['error' => 'User not found'], 404);
            }

            $isAdmin = $userId === 1
                || in_array((string) ($user['role'] ?? ''), ['administrator', 'admin', 'owner'], true);

            // Semua fitur
            $fiturs = $pdo->query(
                'SELECT `key`, `label`, `group`, `sort` FROM fitur_akses ORDER BY `sort` ASC'
            )->fetchAll();

            // Permission yang sudah tersimpan untuk user ini
            $permStmt = $pdo->prepare(
                'SELECT fitur_key, can_access, can_create, can_edit, can_delete
                 FROM user_fitur_akses WHERE user_id = :uid'
            );
            $permStmt->execute([':uid' => $userId]);
            $saved = [];
            foreach ($permStmt->fetchAll() as $row) {
                $saved[(string) ($row['fitur_key'] ?? '')] = $row;
            }

            $result = [];
            foreach (is_array($fiturs) ? $fiturs : [] as $f) {
                $key = (string) ($f['key'] ?? '');
                if ($key === '') continue;

                // Dashboard: default akses = 1
                $defaultAccess = ($key === 'dashboard') ? 1 : 0;

                if ($isAdmin) {
                    // Administrator: semua full akses
                    $result[] = [
                        'key'        => $key,
                        'label'      => (string) ($f['label'] ?? $key),
                        'group'      => (string) ($f['group'] ?? ''),
                        'can_access' => 1,
                        'can_create' => 1,
                        'can_edit'   => 1,
                        'can_delete' => 1,
                        'is_admin'   => true,
                    ];
                } elseif (isset($saved[$key])) {
                    $p = $saved[$key];
                    $result[] = [
                        'key'        => $key,
                        'label'      => (string) ($f['label'] ?? $key),
                        'group'      => (string) ($f['group'] ?? ''),
                        'can_access' => (int) ($p['can_access'] ?? $defaultAccess),
                        'can_create' => (int) ($p['can_create'] ?? 0),
                        'can_edit'   => (int) ($p['can_edit'] ?? 0),
                        'can_delete' => (int) ($p['can_delete'] ?? 0),
                        'is_admin'   => false,
                    ];
                } else {
                    $result[] = [
                        'key'        => $key,
                        'label'      => (string) ($f['label'] ?? $key),
                        'group'      => (string) ($f['group'] ?? ''),
                        'can_access' => $defaultAccess,
                        'can_create' => 0,
                        'can_edit'   => 0,
                        'can_delete' => 0,
                        'is_admin'   => false,
                    ];
                }
            }

            return Response::json(['user_id' => $userId, 'is_admin' => $isAdmin, 'fiturs' => $result]);
        } catch (Throwable) {
            return Response::json(['error' => 'Server error'], 500);
        }
    }

    /** POST /users/{id}/hak-akses — simpan permission */
    public function save(Request $request, string $id): Response
    {
        $userId = (int) $id;
        if ($userId <= 0 || $userId === 1) {
            return Response::json(['error' => 'Invalid user'], 400);
        }

        $body = $request->all();
        $permissions = $body['permissions'] ?? null;

        // Support JSON body
        if ($permissions === null) {
            $raw = file_get_contents('php://input');
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                $permissions = is_array($decoded) ? ($decoded['permissions'] ?? null) : null;
            }
        }

        if (!is_array($permissions)) {
            return Response::json(['error' => 'Invalid payload'], 400);
        }

        try {
            $pdo = Database::connection();

            // Ambil valid keys
            $validKeys = [];
            foreach ($pdo->query('SELECT `key` FROM fitur_akses')->fetchAll() as $r) {
                $validKeys[(string) ($r['key'] ?? '')] = true;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO user_fitur_akses (user_id, fitur_key, can_access, can_create, can_edit, can_delete)
                 VALUES (:uid, :key, :access, :create, :edit, :delete)
                 ON DUPLICATE KEY UPDATE
                   can_access = VALUES(can_access),
                   can_create = VALUES(can_create),
                   can_edit   = VALUES(can_edit),
                   can_delete = VALUES(can_delete)'
            );

            foreach ($permissions as $perm) {
                if (!is_array($perm)) continue;
                $key = trim((string) ($perm['key'] ?? ''));
                if ($key === '' || !isset($validKeys[$key])) continue;

                // Dashboard: akses selalu 1
                $canAccess = ($key === 'dashboard') ? 1 : (int) (bool) ($perm['can_access'] ?? 0);

                $stmt->execute([
                    ':uid'    => $userId,
                    ':key'    => $key,
                    ':access' => $canAccess,
                    ':create' => (int) (bool) ($perm['can_create'] ?? 0),
                    ':edit'   => (int) (bool) ($perm['can_edit'] ?? 0),
                    ':delete' => (int) (bool) ($perm['can_delete'] ?? 0),
                ]);
            }

            return Response::json(['success' => true]);
        } catch (Throwable) {
            return Response::json(['error' => 'Server error'], 500);
        }
    }
}
