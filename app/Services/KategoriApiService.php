<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class KategoriApiService
{
    private const SORTABLE = ['id', 'nama', 'tgl_input', 'created_at', 'updated_at'];

    public function paginate(int $page, int $perPage, string $search, string $sort, string $direction): array
    {
        $pdo = Database::connection();
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'id';
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
        $where = ' WHERE deleted_at IS NULL';
        $bindings = [];
        if ($search !== '') {
            $where .= ' AND (nama LIKE :search_name OR ket LIKE :search_ket)';
            $bindings['search_name'] = '%' . $search . '%';
            $bindings['search_ket'] = '%' . $search . '%';
        }

        $count = $pdo->prepare('SELECT COUNT(*) FROM kategori' . $where);
        $count->execute($bindings);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare(
            'SELECT id, nama, ket, tgl_input, created_at, updated_at FROM kategori' . $where
            . ' ORDER BY `' . $sort . '` ' . $direction . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($bindings as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nama, ket, tgl_input, created_at, updated_at FROM kategori WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function create(array $data): array
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO kategori (nama, ket, tgl_input, created_at, updated_at) VALUES (:nama, :ket, :tgl_input, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute($data);
        return $this->find((int) Database::connection()->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        $stmt = Database::connection()->prepare(
            'UPDATE kategori SET nama = :nama, ket = :ket, tgl_input = :tgl_input, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute($data + ['id' => $id]);
        return $stmt->rowCount() > 0 ? $this->find($id) : $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE kategori SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
