<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class SatuanApiService
{
    private const SORTABLE = ['id', 'nama', 'created_at', 'updated_at'];

    public function paginate(int $page, int $perPage, string $search, string $sort, string $direction): array
    {
        $pdo = Database::connection();
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'id';
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
        $where = ' WHERE deleted_at IS NULL';
        $bindings = [];
        if ($search !== '') {
            $where .= ' AND (nama LIKE :search OR ket LIKE :search)';
            $bindings['search'] = '%' . $search . '%';
        }
        $count = $pdo->prepare('SELECT COUNT(*) FROM satuan' . $where);
        $count->execute($bindings);
        $total = (int) $count->fetchColumn();
        $stmt = $pdo->prepare(
            'SELECT id, nama, ket, created_at, updated_at FROM satuan' . $where
            . ' ORDER BY `' . $sort . '` ' . $direction . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($bindings as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nama, ket, created_at, updated_at FROM satuan WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function create(array $data): array
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO satuan (nama, ket, created_at, updated_at) VALUES (:nama, :ket, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute($data);
        return $this->find((int) Database::connection()->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        $stmt = Database::connection()->prepare(
            'UPDATE satuan SET nama = :nama, ket = :ket, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute($data + ['id' => $id]);
        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE satuan SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
