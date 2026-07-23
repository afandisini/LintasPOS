<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class MasterDataApiService
{
    public function paginate(string $table, array $fields, int $page, int $perPage, string $search, string $sort, string $direction, array $filters = []): array
    {
        $pdo = Database::connection();
        $columns = array_values(array_unique(array_merge(['id'], $fields, ['created_at', 'updated_at'])));
        $sort = in_array($sort, $columns, true) ? $sort : 'id';
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
        $where = ' WHERE deleted_at IS NULL';
        $bindings = [];
        $searchFields = array_values(array_filter($fields, static fn(string $field): bool => !in_array($field, ['kategori_id', 'satuan_id', 'gambar', 'gambar_img', 'harga', 'harga_beli', 'harga_jual', 'stok', 'diskon'], true)));
        if ($search !== '' && $searchFields !== []) {
            $parts = [];
            foreach ($searchFields as $index => $field) {
                $param = 'search_' . $index;
                $parts[] = '`' . $field . '` LIKE :' . $param;
                $bindings[$param] = '%' . $search . '%';
            }
            $where .= ' AND (' . implode(' OR ', $parts) . ')';
        }
        foreach ($filters as $field => $value) {
            if (!in_array($field, $fields, true) || $value === '' || $value === null) continue;
            $param = 'filter_' . $field;
            $where .= ' AND `' . $field . '` = :' . $param;
            $bindings[$param] = $value;
        }
        $select = implode(', ', array_map(static fn(string $field): string => '`' . $field . '`', $columns));
        $count = $pdo->prepare('SELECT COUNT(*) FROM `' . $table . '`' . $where);
        $count->execute($bindings);
        $total = (int) $count->fetchColumn();
        $stmt = $pdo->prepare('SELECT ' . $select . ' FROM `' . $table . '`' . $where . ' ORDER BY `' . $sort . '` ' . $direction . ' LIMIT :limit OFFSET :offset');
        foreach ($bindings as $key => $value) $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function find(string $table, array $fields, int $id): ?array
    {
        $columns = array_values(array_unique(array_merge(['id'], $fields, ['created_at', 'updated_at'])));
        $stmt = Database::connection()->prepare('SELECT ' . implode(', ', array_map(static fn(string $field): string => '`' . $field . '`', $columns)) . ' FROM `' . $table . '` WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function create(string $table, array $fields, array $data): array
    {
        $columns = array_keys($data);
        $stmt = Database::connection()->prepare('INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . '`, `created_at`, `updated_at`) VALUES (:' . implode(', :', $columns) . ', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
        $stmt->execute($data);
        return $this->find($table, $fields, (int) Database::connection()->lastInsertId()) ?? [];
    }

    public function update(string $table, array $fields, int $id, array $data): ?array
    {
        $set = implode(', ', array_map(static fn(string $field): string => '`' . $field . '` = :' . $field, array_keys($data)));
        $stmt = Database::connection()->prepare('UPDATE `' . $table . '` SET ' . $set . ', `updated_at` = CURRENT_TIMESTAMP WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute($data + ['id' => $id]);
        return $this->find($table, $fields, $id);
    }

    public function delete(string $table, int $id): bool
    {
        $stmt = Database::connection()->prepare('UPDATE `' . $table . '` SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
