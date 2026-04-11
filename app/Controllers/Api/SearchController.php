<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\Database;
use System\Http\Request;
use System\Http\Response;
use Throwable;

class SearchController
{
    private const LIMIT = 4;

    public function index(Request $request): Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        if ($auth === []) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $role  = strtolower(trim((string) ($auth['role'] ?? '')));
        $q     = trim((string) ($request->all()['q'] ?? ''));

        if (strlen($q) < 2) {
            return Response::json(['results' => []]);
        }

        $like = '%' . $q . '%';
        $results = [];

        try {
            $pdo = Database::connection();

            // Barang
            $stmt = $pdo->prepare(
                "SELECT id, nama_barang AS label, id_barang AS sub, harga_jual AS meta, stok AS extra
                 FROM barang WHERE deleted_at IS NULL AND (nama_barang LIKE :q OR id_barang LIKE :q2)
                 LIMIT " . self::LIMIT
            );
            $stmt->execute([':q' => $like, ':q2' => $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'type'  => 'barang',
                    'icon'  => 'bi-box-seam',
                    'label' => (string) ($row['label'] ?? ''),
                    'sub'   => (string) ($row['sub'] ?? ''),
                    'meta'  => 'Rp ' . number_format((int) ($row['meta'] ?? 0), 0, ',', '.') . ' · stok ' . (int) ($row['extra'] ?? 0),
                    'url'   => '/barang',
                ];
            }

            // Jasa
            $stmt = $pdo->prepare(
                "SELECT id, nama AS label, id_jasa AS sub, harga AS meta
                 FROM jasa WHERE deleted_at IS NULL AND (nama LIKE :q OR id_jasa LIKE :q2)
                 LIMIT " . self::LIMIT
            );
            $stmt->execute([':q' => $like, ':q2' => $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'type'  => 'jasa',
                    'icon'  => 'bi-wrench',
                    'label' => (string) ($row['label'] ?? ''),
                    'sub'   => (string) ($row['sub'] ?? ''),
                    'meta'  => 'Rp ' . number_format((int) ($row['meta'] ?? 0), 0, ',', '.'),
                    'url'   => '/jasa',
                ];
            }

            // Pelanggan
            $stmt = $pdo->prepare(
                "SELECT id, nama_pelanggan AS label, kode_pelanggan AS sub, telepon_pelanggan AS meta
                 FROM pelanggan WHERE deleted_at IS NULL AND (nama_pelanggan LIKE :q OR kode_pelanggan LIKE :q2 OR telepon_pelanggan LIKE :q3)
                 LIMIT " . self::LIMIT
            );
            $stmt->execute([':q' => $like, ':q2' => $like, ':q3' => $like]);
            foreach ($stmt->fetchAll() as $row) {
                $results[] = [
                    'type'  => 'pelanggan',
                    'icon'  => 'bi-person',
                    'label' => (string) ($row['label'] ?? ''),
                    'sub'   => (string) ($row['sub'] ?? ''),
                    'meta'  => (string) ($row['meta'] ?? ''),
                    'url'   => '/pelanggan',
                ];
            }

            // Supplier — hanya admin/spv/owner
            if (in_array($role, ['admin', 'spv', 'owner', 'administrator'], true)) {
                $stmt = $pdo->prepare(
                    "SELECT id, nama_supplier AS label, telepon_supplier AS sub, email_supplier AS meta
                     FROM supplier WHERE deleted_at IS NULL AND (nama_supplier LIKE :q OR telepon_supplier LIKE :q2)
                     LIMIT " . self::LIMIT
                );
                $stmt->execute([':q' => $like, ':q2' => $like]);
                foreach ($stmt->fetchAll() as $row) {
                    $results[] = [
                        'type'  => 'supplier',
                        'icon'  => 'bi-truck',
                        'label' => (string) ($row['label'] ?? ''),
                        'sub'   => (string) ($row['sub'] ?? ''),
                        'meta'  => (string) ($row['meta'] ?? ''),
                        'url'   => '/supplier',
                    ];
                }
            }
        } catch (Throwable) {
            return Response::json(['results' => []]);
        }

        return Response::json(['results' => $results]);
    }
}
