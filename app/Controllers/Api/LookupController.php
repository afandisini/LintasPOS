<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\ApiResponse;
use App\Services\Database;
use PDO;
use System\Http\Request;
use System\Http\Response;
use Throwable;

final class LookupController
{
    private const DEFINITIONS = [
        'barang' => ['table' => 'barang', 'value' => 'id_barang', 'label' => 'nama_barang'],
        'jasa' => ['table' => 'jasa', 'value' => 'id_jasa', 'label' => 'nama'],
        'pelanggan' => ['table' => 'pelanggan', 'value' => 'id', 'label' => 'nama_pelanggan'],
        'supplier' => ['table' => 'supplier', 'value' => 'id', 'label' => 'nama_supplier'],
    ];

    public function index(Request $request, string $resource): Response
    {
        $definition = self::DEFINITIONS[$resource] ?? null;
        if ($definition === null) return ApiResponse::error('NOT_FOUND', 'Lookup tidak ditemukan.', 404);
        try {
            $search = trim((string) ($request->input('search', '')));
            $limit = min(50, max(1, (int) $request->input('limit', 20)));
            $where = ' WHERE deleted_at IS NULL';
            $bindings = [];
            if ($search !== '') {
                $where .= ' AND `' . $definition['label'] . '` LIKE :search';
                $bindings['search'] = '%' . $search . '%';
            }
            $sql = 'SELECT `' . $definition['value'] . '` AS value, `' . $definition['label'] . '` AS label FROM `' . $definition['table'] . '`' . $where . ' ORDER BY label ASC LIMIT :limit';
            $stmt = Database::connection()->prepare($sql);
            foreach ($bindings as $key => $value) $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return ApiResponse::success($stmt->fetchAll(PDO::FETCH_ASSOC), 200, ['limit' => $limit]);
        } catch (Throwable) {
            return ApiResponse::error('SERVER_ERROR', 'Gagal memuat lookup.', 500);
        }
    }
}
