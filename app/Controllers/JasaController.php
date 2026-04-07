<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use System\Http\Request;
use System\Http\Response;
use RuntimeException;
use Throwable;

class JasaController
{
    private const MAX_IMAGE_SIZE = 5_242_880; // 5MB

    public function index(Request $request): Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $rows = [];
        $editableColumns = [];
        $displayColumns = [];
        $relations = [];
        $totalRows = 0;

        try {
            $pdo = Database::connection();
            $editableColumns = $this->editableColumns($pdo);
            $relations = $this->buildRelations($pdo, $editableColumns);
            $stmt = $pdo->query('SELECT * FROM `jasa` ORDER BY id DESC');
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                $rows = [];
            }
            $rows = $this->decorateRowsWithRelations($rows, $relations);
            $totalRows = (int) $pdo->query('SELECT COUNT(*) FROM `jasa`')->fetchColumn();
            $displayColumns = $this->displayColumns($rows, $editableColumns);
            $rows = $this->decorateRowsWithHelpers($pdo, $rows, $displayColumns);
        } catch (Throwable) {
            toast_add('Gagal memuat data Jasa.', 'error');
        }

        $html = app()->view()->render('jasa/index', [
            'title' => 'Jasa',
            'auth' => $auth,
            'activeMenu' => 'jasa',
            'rows' => $rows,
            'totalRows' => $totalRows,
            'editableColumns' => $editableColumns,
            'displayColumns' => $displayColumns,
            'relations' => $relations,
            'routePrefix' => 'jasa',
        ]);

        return Response::html($html);
    }

    public function datatable(Request $request): Response
    {
        try {
            $pdo = Database::connection();
            $editableColumns = $this->editableColumns($pdo);
            $displayColumns = $this->displayColumns([], $editableColumns);
            $relations = $this->buildRelations($pdo, $editableColumns);
            $params = $request->all();

            $draw = max(0, (int) ($params['draw'] ?? 0));
            $start = max(0, (int) ($params['start'] ?? 0));
            $length = (int) ($params['length'] ?? 10);
            if ($length < 1) {
                $length = 10;
            }
            if ($length > 100) {
                $length = 100;
            }

            $search = trim((string) (($params['search']['value'] ?? '') ?: ''));

            $orderable = ['id'];
            foreach ($displayColumns as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $name = trim((string) ($column['name'] ?? ''));
                if ($name !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                    $orderable[] = $name;
                }
            }
            $orderable = array_values(array_unique($orderable));
            $orderMap = [0 => 'id'];
            $displayOffset = 1;
            foreach ($displayColumns as $idx => $column) {
                if (!is_array($column)) {
                    continue;
                }
                $name = trim((string) ($column['name'] ?? ''));
                if ($name !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                    $orderMap[$displayOffset + (int) $idx] = $name;
                }
            }

            $orderIndex = (int) ($params['order'][0]['column'] ?? 0);
            $orderColumn = $orderMap[$orderIndex] ?? 'id';
            $orderDir = strtolower((string) ($params['order'][0]['dir'] ?? 'desc'));
            $orderDir = $orderDir === 'asc' ? 'asc' : 'desc';

            $whereSql = '';
            $bindings = [];
            if ($search !== '') {
                $whereParts = [];
                foreach ($orderable as $idx => $columnName) {
                    $param = 'search_' . $idx;
                    if ($columnName === 'id') {
                        $whereParts[] = 'CAST(`id` AS CHAR) LIKE :' . $param;
                    } else {
                        $whereParts[] = '`' . $columnName . '` LIKE :' . $param;
                    }
                    $bindings[$param] = '%' . $search . '%';
                }
                if ($whereParts !== []) {
                    $whereSql = ' WHERE (' . implode(' OR ', $whereParts) . ')';
                }
            }

            $countTotal = (int) $pdo->query('SELECT COUNT(*) FROM `jasa`')->fetchColumn();
            if ($whereSql === '') {
                $countFiltered = $countTotal;
            } else {
                $countStmt = $pdo->prepare('SELECT COUNT(*) FROM `jasa`' . $whereSql);
                foreach ($bindings as $key => $value) {
                    $countStmt->bindValue(':' . $key, $value);
                }
                $countStmt->execute();
                $countFiltered = (int) $countStmt->fetchColumn();
            }

            $sql = 'SELECT * FROM `jasa`' . $whereSql . ' ORDER BY `' . $orderColumn . '` ' . $orderDir . ' LIMIT :limit OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            foreach ($bindings as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', $length, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $start, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                $rows = [];
            }
            $rows = $this->decorateRowsWithRelations($rows, $relations);
            $rows = $this->decorateRowsWithHelpers($pdo, $rows, $displayColumns);

            return Response::json([
                'draw' => $draw,
                'recordsTotal' => $countTotal,
                'recordsFiltered' => $countFiltered,
                'data' => $rows,
            ]);
        } catch (Throwable) {
            return Response::json([
                'draw' => max(0, (int) ($request->all()['draw'] ?? 0)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Gagal memuat data Jasa.',
            ], 500);
        }
    }

    public function store(Request $request): Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $authId = (int) ($auth['id'] ?? 0);
        $createdFileIds = [];
        $createdAbsolutePaths = [];

        try {
            $pdo = Database::connection();
            $columns = $this->editableColumns($pdo);
            if ($columns === []) {
                toast_add('Kolom input tidak tersedia.', 'warning');
                return Response::redirect('/jasa');
            }

            $insertColumns = [];
            $params = [];
            $missingLabels = [];
            foreach ($columns as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $name = (string) ($column['name'] ?? '');
                $label = (string) ($column['label'] ?? $name);
                $inputType = (string) ($column['input_type'] ?? 'text');
                $required = (bool) ($column['required'] ?? false);
                if ($name === '') {
                    continue;
                }
                $value = null;
                if ($inputType === 'file' && $this->isImageColumnName($name)) {
                    $uploadResult = $this->handleJasaImageUpload($pdo, $name, $auth, $authId);
                    if ($uploadResult['file_id'] > 0) {
                        $value = (string) $uploadResult['file_id'];
                        $createdFileIds[] = (int) $uploadResult['file_id'];
                        if ($uploadResult['absolute_path'] !== '') {
                            $createdAbsolutePaths[] = (string) $uploadResult['absolute_path'];
                        }
                    } else {
                        $value = '';
                    }
                } elseif ($this->isAutoUpdateDateField($name)) {
                    $value = $inputType === 'datetime-local' ? date('Y-m-d H:i:s') : date('Y-m-d');
                } else {
                    $value = trim((string) $request->input($name, ''));
                }
                if ($value === '' && $required) {
                    if ($inputType === 'date') {
                        $value = date('Y-m-d');
                    } elseif ($inputType === 'datetime-local') {
                        $value = date('Y-m-d H:i:s');
                    }
                }
                if ($value === '' && $required) {
                    $missingLabels[] = $label !== '' ? $label : $name;
                    continue;
                }
                $insertColumns[] = $name;
                $params[$name] = $value !== '' ? $value : null;
            }

            if ($missingLabels !== []) {
                toast_add('Field wajib belum terisi: ' . implode(', ', array_slice($missingLabels, 0, 3)), 'error');
                return Response::redirect('/jasa');
            }

            if ($insertColumns === []) {
                toast_add('Data input tidak valid.', 'error');
                return Response::redirect('/jasa');
            }

            $columnSql = '`' . implode('`,`', $insertColumns) . '`';
            $valueSql = ':' . implode(',:', $insertColumns);
            $stmt = $pdo->prepare('INSERT INTO `jasa` (' . $columnSql . ') VALUES (' . $valueSql . ')');
            $stmt->execute($params);
            toast_add('Data Jasa berhasil ditambahkan.', 'success');
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'upload') !== false || stripos($e->getMessage(), 'gambar') !== false) {
                toast_add($e->getMessage(), 'error');
            } else {
                toast_add('Gagal menambahkan data Jasa.', 'error');
            }
            $this->rollbackUploadedFiles($pdo ?? null, $createdFileIds, $createdAbsolutePaths);
            return Response::redirect('/jasa');
        }

        return Response::redirect('/jasa');
    }

    public function update(Request $request, string $id): Response
    {
        $recordId = (int) $id;
        if ($recordId <= 0) {
            toast_add('ID data tidak valid.', 'error');
            return Response::redirect('/jasa');
        }

        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $authId = (int) ($auth['id'] ?? 0);
        $createdFileIds = [];
        $createdAbsolutePaths = [];
        $oldFileIdsToDelete = [];

        try {
            $pdo = Database::connection();
            $columns = $this->editableColumns($pdo);
            if ($columns === []) {
                toast_add('Kolom input tidak tersedia.', 'warning');
                return Response::redirect('/jasa');
            }

            $setParts = [];
            $params = ['id' => $recordId];
            $missingLabels = [];
            foreach ($columns as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $name = (string) ($column['name'] ?? '');
                $label = (string) ($column['label'] ?? $name);
                $inputType = (string) ($column['input_type'] ?? 'text');
                $required = (bool) ($column['required'] ?? false);
                if ($name === '') {
                    continue;
                }
                $value = null;
                if ($inputType === 'file' && $this->isImageColumnName($name)) {
                    $currentValue = trim((string) $request->input('current_' . $name, ''));
                    $uploadResult = $this->handleJasaImageUpload($pdo, $name, $auth, $authId);
                    if ($uploadResult['file_id'] > 0) {
                        $value = (string) $uploadResult['file_id'];
                        $createdFileIds[] = (int) $uploadResult['file_id'];
                        if ($uploadResult['absolute_path'] !== '') {
                            $createdAbsolutePaths[] = (string) $uploadResult['absolute_path'];
                        }
                        if ($currentValue !== '' && ctype_digit($currentValue)) {
                            $oldFileIdsToDelete[] = (int) $currentValue;
                        }
                    } else {
                        $value = $currentValue;
                    }
                } elseif ($this->isAutoUpdateDateField($name)) {
                    $value = $inputType === 'datetime-local' ? date('Y-m-d H:i:s') : date('Y-m-d');
                } else {
                    $value = trim((string) $request->input($name, ''));
                }
                if ($value === '' && $required) {
                    if ($inputType === 'date') {
                        $value = date('Y-m-d');
                    } elseif ($inputType === 'datetime-local') {
                        $value = date('Y-m-d H:i:s');
                    }
                }
                if ($value === '' && $required) {
                    $missingLabels[] = $label !== '' ? $label : $name;
                    continue;
                }
                $setParts[] = '`' . $name . '` = :' . $name;
                $params[$name] = $value !== '' ? $value : null;
            }

            if ($missingLabels !== []) {
                toast_add('Field wajib belum terisi: ' . implode(', ', array_slice($missingLabels, 0, 3)), 'error');
                return Response::redirect('/jasa');
            }

            if ($setParts === []) {
                toast_add('Data input tidak valid.', 'error');
                return Response::redirect('/jasa');
            }

            $sql = 'UPDATE `jasa` SET ' . implode(', ', $setParts) . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            foreach (array_values(array_unique($oldFileIdsToDelete)) as $oldFileId) {
                $this->softDeleteFilemanagerById($pdo, (int) $oldFileId);
            }

            toast_add('Data Jasa berhasil diperbarui.', 'success');
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'upload') !== false || stripos($e->getMessage(), 'gambar') !== false) {
                toast_add($e->getMessage(), 'error');
            } else {
                toast_add('Gagal memperbarui data Jasa.', 'error');
            }
            $this->rollbackUploadedFiles($pdo ?? null, $createdFileIds, $createdAbsolutePaths);
            return Response::redirect('/jasa');
        }

        return Response::redirect('/jasa');
    }

    public function destroy(Request $request, string $id): Response
    {
        $recordId = (int) $id;
        if ($recordId <= 0) {
            toast_add('ID data tidak valid.', 'error');
            return Response::redirect('/jasa');
        }

        try {
            $pdo = Database::connection();
            $fileIdsToDelete = [];
            $columns = $this->editableColumns($pdo);
            if ($columns !== []) {
                $stmtCurrent = $pdo->prepare('SELECT * FROM `jasa` WHERE id = :id LIMIT 1');
                $stmtCurrent->execute(['id' => $recordId]);
                $currentRow = $stmtCurrent->fetch(\PDO::FETCH_ASSOC);
                if (is_array($currentRow)) {
                    foreach ($columns as $column) {
                        if (!is_array($column)) {
                            continue;
                        }
                        $name = (string) ($column['name'] ?? '');
                        if ($name === '' || !$this->isImageColumnName($name)) {
                            continue;
                        }
                        $rawValue = trim((string) ($currentRow[$name] ?? ''));
                        if ($rawValue !== '' && ctype_digit($rawValue)) {
                            $fileIdsToDelete[] = (int) $rawValue;
                        }
                    }
                }
            }

            $stmt = $pdo->prepare('DELETE FROM `jasa` WHERE id = :id');
            $stmt->execute(['id' => $recordId]);

            foreach (array_values(array_unique($fileIdsToDelete)) as $fileId) {
                $this->softDeleteFilemanagerById($pdo, (int) $fileId);
            }
            toast_add('Data Jasa berhasil dihapus.', 'success');
        } catch (Throwable) {
            toast_add('Gagal menghapus data Jasa.', 'error');
        }

        return Response::redirect('/jasa');
    }

    /**
     * @return array<int, array{name: string, label: string, input_type: string, required: bool, relation_table: string|null}>
     */
    private function isAutoUpdateDateField(string $fieldName): bool
    {
        $normalized = strtolower(trim($fieldName));
        return in_array($normalized, ['tgl_update', 'tangal_updata'], true);
    }

    /**
     * @return array<int, array{name: string, label: string, input_type: string, required: bool, relation_table: string|null}>
     */
    private function editableColumns(\PDO $pdo): array
    {
        $rows = [];
        try {
            $stmt = $pdo->prepare(
                'SELECT COLUMN_NAME, DATA_TYPE '
                . 'FROM INFORMATION_SCHEMA.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table '
                . 'ORDER BY ORDINAL_POSITION ASC'
            );
            $stmt->execute(['table' => 'jasa']);
            $infoRows = $stmt->fetchAll();
            if (is_array($infoRows)) {
                $rows = $infoRows;
            }
        } catch (Throwable) {
            $rows = [];
        }

        if ($rows === []) {
            try {
                $descRows = $pdo->query('DESCRIBE `jasa`')->fetchAll();
                if (is_array($descRows)) {
                    foreach ($descRows as $descRow) {
                        if (!is_array($descRow)) {
                            continue;
                        }
                        $rows[] = [
                            'COLUMN_NAME' => (string) ($descRow['Field'] ?? ''),
                            'DATA_TYPE' => strtolower((string) ($descRow['Type'] ?? 'varchar')),
                            'IS_NULLABLE' => strtoupper((string) ($descRow['Null'] ?? 'YES')) === 'YES' ? 'YES' : 'NO',
                            'COLUMN_DEFAULT' => $descRow['Default'] ?? null,
                        ];
                    }
                }
            } catch (Throwable) {
                $rows = [];
            }
        }

        if ($rows === []) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = strtolower(trim((string) ($row['COLUMN_NAME'] ?? '')));
            if ($name === '' || in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            $dataType = strtolower((string) ($row['DATA_TYPE'] ?? 'varchar'));
            if (str_contains($dataType, '(')) {
                $dataType = (string) strstr($dataType, '(', true);
            }
            $nullable = strtoupper((string) ($row['IS_NULLABLE'] ?? 'YES')) === 'YES';
            $defaultValue = $row['COLUMN_DEFAULT'] ?? null;
            $required = !$nullable && $defaultValue === null;
            $inputType = 'text';
            $relationTable = null;
            if ($this->isImageColumnName($name)) {
                $inputType = 'file';
            } elseif (str_ends_with($name, '_id') && $name !== 'id') {
                $inputType = 'select';
                $relationTable = substr($name, 0, -3) ?: null;
            } elseif (str_ends_with($name, '_textarea') || in_array($dataType, ['text', 'longtext', 'mediumtext'], true)) {
                $inputType = 'textarea';
            } elseif (in_array($dataType, ['date'], true)) {
                $inputType = 'date';
            } elseif (in_array($dataType, ['datetime', 'timestamp'], true)) {
                $inputType = 'datetime-local';
            } elseif (in_array($dataType, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'double', 'float'], true)) {
                $inputType = 'number';
            }

            $result[] = [
                'name' => $name,
                'label' => ucwords(str_replace('_', ' ', preg_replace('/(_id|_img|_file|_textarea)$/', '', $name) ?: $name)),
                'input_type' => $inputType,
                'required' => $required,
                'relation_table' => $relationTable,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array{name: string, label: string, input_type: string, required: bool, relation_table: string|null}> $editableColumns
     * @return array<int, array{name: string, label: string}>
     */
    private function displayColumns(array $rows, array $editableColumns): array
    {
        $picked = [];
        foreach ($editableColumns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $name = (string) ($column['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $picked[] = [
                'name' => $name,
                'label' => (string) ($column['label'] ?? $name),
            ];
        }

        if ($picked !== []) {
            return $picked;
        }

        if ($rows !== [] && is_array($rows[0])) {
            foreach (array_keys($rows[0]) as $key) {
                $name = (string) $key;
                if (
                    $name === ''
                    || ctype_digit($name)
                    || in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at'], true)
                ) {
                    continue;
                }
                $picked[] = [
                    'name' => $name,
                    'label' => ucwords(str_replace('_', ' ', $name)),
                ];
            }
        }

        return $picked;
    }

    /**
     * @param array<int, array{name: string, label: string, input_type: string, required: bool, relation_table: string|null}> $editableColumns
     * @return array<string, array{table: string, key: string, label: string, options: array<int, array{id: string, label: string}>}>
     */
    private function buildRelations(\PDO $pdo, array $editableColumns): array
    {
        $relations = [];
        foreach ($editableColumns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $name = trim((string) ($column['name'] ?? ''));
            $inputType = (string) ($column['input_type'] ?? 'text');
            $table = trim((string) ($column['relation_table'] ?? ''));
            if ($name === '' || $inputType !== 'select' || $table === '') {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                continue;
            }
            if (!$this->tableExists($pdo, $table)) {
                continue;
            }

            $labelColumn = $this->pickRelationLabelColumn($pdo, $table);
            $stmt = $pdo->query('SELECT `id`, `' . $labelColumn . '` AS `label` FROM `' . $table . '` ORDER BY `id` ASC');
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $options = [];
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $options[] = [
                        'id' => (string) ($row['id'] ?? ''),
                        'label' => (string) ($row['label'] ?? ''),
                    ];
                }
            }

            $relations[$name] = [
                'table' => $table,
                'key' => 'id',
                'label' => $labelColumn,
                'options' => $options,
            ];
        }

        return $relations;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, array{table: string, key: string, label: string, options: array<int, array{id: string, label: string}>}> $relations
     * @return array<int, array<string, mixed>>
     */
    private function decorateRowsWithRelations(array $rows, array $relations): array
    {
        if ($rows === [] || $relations === []) {
            return $rows;
        }

        $maps = [];
        foreach ($relations as $field => $meta) {
            $map = [];
            $options = $meta['options'] ?? [];
            if (is_array($options)) {
                foreach ($options as $option) {
                    if (!is_array($option)) {
                        continue;
                    }
                    $id = (string) ($option['id'] ?? '');
                    $label = (string) ($option['label'] ?? '');
                    if ($id !== '') {
                        $map[$id] = $label !== '' ? $label : $id;
                    }
                }
            }
            $maps[$field] = $map;
        }

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($maps as $field => $map) {
                $raw = (string) ($row[$field] ?? '');
                if ($raw === '') {
                    continue;
                }
                $rows[$index][$field . '__label'] = (string) ($map[$raw] ?? $raw);
            }
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array{name: string, label: string}> $displayColumns
     * @return array<int, array<string, mixed>>
     */
    private function decorateRowsWithHelpers(\PDO $pdo, array $rows, array $displayColumns): array
    {
        if ($rows === [] || $displayColumns === []) {
            return $rows;
        }

        $imageColumns = $this->detectImageColumns($displayColumns);
        $currencyColumns = $this->detectCurrencyColumns($displayColumns);
        $filemanagerPathMap = $this->buildFilemanagerPathMap($pdo, $rows, $imageColumns);

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($currencyColumns as $column) {
                $rawValue = $row[$column] ?? null;
                $rows[$index][$column . '__display'] = format_currency_id($rawValue);
            }

            foreach ($imageColumns as $column) {
                $rawValue = trim((string) ($row[$column] ?? ''));
                $resolvedValue = $rawValue;
                if ($rawValue !== '' && ctype_digit($rawValue)) {
                    $resolvedValue = (string) ($filemanagerPathMap[(int) $rawValue] ?? $rawValue);
                }
                $rows[$index][$column . '__html'] = render_image_cell($resolvedValue, (string) ($column));
                $rows[$index][$column . '__display'] = $resolvedValue !== '' ? $resolvedValue : '-';
            }
        }

        return $rows;
    }

    /**
     * @param array<int, array{name: string, label: string}> $displayColumns
     * @return array<int, string>
     */
    private function detectImageColumns(array $displayColumns): array
    {
        $result = [];
        foreach ($displayColumns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $name = strtolower(trim((string) ($column['name'] ?? '')));
            if ($name === '') {
                continue;
            }
            if (preg_match('/(^|_)(gambar|image|img|foto|photo|avatar|logo|thumbnail|thumb|cover|icon|banner)($|_)/', $name)) {
                $result[] = $name;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param array<int, array{name: string, label: string}> $displayColumns
     * @return array<int, string>
     */
    private function detectCurrencyColumns(array $displayColumns): array
    {
        $result = [];
        foreach ($displayColumns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $name = strtolower(trim((string) ($column['name'] ?? '')));
            if ($name === '') {
                continue;
            }
            if (preg_match('/(^|_)(harga|price|amount|nominal|total|biaya|bayar|subtotal|saldo)($|_)/', $name)) {
                $result[] = $name;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $imageColumns
     * @return array<int, string>
     */
    private function buildFilemanagerPathMap(\PDO $pdo, array $rows, array $imageColumns): array
    {
        if ($rows === [] || $imageColumns === []) {
            return [];
        }

        $fileIds = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($imageColumns as $column) {
                $raw = trim((string) ($row[$column] ?? ''));
                if ($raw !== '' && ctype_digit($raw)) {
                    $id = (int) $raw;
                    if ($id > 0) {
                        $fileIds[$id] = true;
                    }
                }
            }
        }

        if ($fileIds === []) {
            return [];
        }

        $idList = array_keys($fileIds);
        $idParams = [];
        $placeholders = [];
        foreach ($idList as $idx => $idValue) {
            $param = ':fid' . $idx;
            $placeholders[] = $param;
            $idParams[$param] = (int) $idValue;
        }
        $inSql = implode(',', $placeholders);
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $authId = (int) ($auth['id'] ?? 0);
        $isAdmin = $this->isAdministrator($auth);

        $sql = 'SELECT id, path, uploaded_by FROM filemanager WHERE deleted_at IS NULL AND module = :module AND id IN (' . $inSql . ')';
        if (!$isAdmin) {
            $sql .= ' AND uploaded_by = :uploaded_by';
        }

        $stmt = $pdo->prepare($sql);
        foreach ($idParams as $param => $fileId) {
            $stmt->bindValue($param, $fileId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':module', 'jasa');
        if (!$isAdmin) {
            $stmt->bindValue(':uploaded_by', $authId, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $map = [];
        $rowsDb = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($rowsDb)) {
            return [];
        }
        foreach ($rowsDb as $rowDb) {
            if (!is_array($rowDb)) {
                continue;
            }
            $id = (int) ($rowDb['id'] ?? 0);
            $path = ltrim((string) ($rowDb['path'] ?? ''), '/');
            if ($id > 0 && $path !== '' && str_starts_with($path, 'filemanager/')) {
                $map[$id] = $path;
            }
        }

        return $map;
    }

    /**
     * @return array{file_id:int, absolute_path:string}
     */
    private function handleJasaImageUpload(\PDO $pdo, string $fieldName, array $auth, int $authId): array
    {
        $uploaded = $_FILES[$fieldName] ?? null;
        if (!is_array($uploaded) || (int) ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['file_id' => 0, 'absolute_path' => ''];
        }
        if ((int) ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload gambar tidak valid.');
        }

        $tmp = (string) ($uploaded['tmp_name'] ?? '');
        $originalName = trim((string) ($uploaded['name'] ?? ''));
        $size = (int) ($uploaded['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0) {
            throw new RuntimeException('File gambar gagal diproses.');
        }
        if ($size > self::MAX_IMAGE_SIZE) {
            throw new RuntimeException('Ukuran gambar melebihi batas 5MB.');
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Format gambar harus JPG, PNG, WEBP, atau GIF.');
        }

        $mimeType = (string) mime_content_type($tmp);
        if (!str_starts_with($mimeType, 'image/')) {
            throw new RuntimeException('File upload harus berupa gambar.');
        }

        $roleRaw = (string) ($auth['role'] ?? 'users');
        $roleSegment = $this->normalizePathSegment($roleRaw);
        if ($roleSegment === '') {
            $roleSegment = 'users';
        }
        $userSegment = (string) ($authId > 0 ? $authId : 0);

        $baseStorage = app()->basePath('storage/filemanager/jasa/' . $roleSegment . '/' . $userSegment);
        if (!is_dir($baseStorage) && !mkdir($baseStorage, 0775, true) && !is_dir($baseStorage)) {
            throw new RuntimeException('Gagal membuat direktori upload gambar jasa.');
        }

        $storedName = 'jasa_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = $baseStorage . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file($tmp, $targetPath)) {
            throw new RuntimeException('Gagal menyimpan gambar jasa ke storage.');
        }

        $relativePath = 'filemanager/jasa/' . $roleSegment . '/' . $userSegment . '/' . $storedName;
        $fileId = $this->insertFilemanagerRecord(
            $pdo,
            $authId,
            $originalName !== '' ? $originalName : $storedName,
            'jasa',
            $userSegment,
            $relativePath,
            $storedName,
            $mimeType,
            $extension,
            $size
        );
        if ($fileId <= 0) {
            @unlink($targetPath);
            throw new RuntimeException('Gagal menyimpan metadata gambar jasa.');
        }

        return [
            'file_id' => $fileId,
            'absolute_path' => $targetPath,
        ];
    }

    private function isImageColumnName(string $name): bool
    {
        return (bool) preg_match('/(^|_)(gambar|image|img|foto|photo|avatar|logo|thumbnail|thumb|cover|icon|banner)($|_)/', strtolower(trim($name)));
    }

    private function normalizePathSegment(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]/', '-', $value);
        $value = is_string($value) ? trim($value, '-') : '';
        return is_string($value) ? substr($value, 0, 64) : '';
    }

    private function isAdministrator(array $auth): bool
    {
        $role = strtolower(trim((string) ($auth['role'] ?? '')));
        if ($role === '') {
            return false;
        }

        return in_array($role, ['administrator', 'admin', 'superadmin', 'super-admin'], true);
    }

    private function insertFilemanagerRecord(
        \PDO $pdo,
        int $uploadedBy,
        string $displayName,
        string $module,
        string $refId,
        string $path,
        string $filename,
        string $mimeType,
        string $extension,
        int $sizeBytes
    ): int {
        $stmt = $pdo->prepare(
            'INSERT INTO filemanager (`name`, module, ref_id, path, filename, mime_type, extension, size_bytes, visibility, uploaded_by, checksum_sha1, create_time, created_at, updated_at) '
            . 'VALUES (:name, :module, :ref_id, :path, :filename, :mime_type, :extension, :size_bytes, :visibility, :uploaded_by, :checksum_sha1, NOW(), NOW(), NOW())'
        );
        $stmt->execute([
            'name' => $this->sanitizeFileName($displayName),
            'module' => $module,
            'ref_id' => $refId,
            'path' => $path,
            'filename' => $filename,
            'mime_type' => $mimeType !== '' ? $mimeType : null,
            'extension' => $extension !== '' ? $extension : null,
            'size_bytes' => $sizeBytes > 0 ? $sizeBytes : null,
            'visibility' => 'private',
            'uploaded_by' => $uploadedBy > 0 ? $uploadedBy : null,
            'checksum_sha1' => null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<int,int> $fileIds
     * @param array<int,string> $absolutePaths
     */
    private function rollbackUploadedFiles(?\PDO $pdo, array $fileIds, array $absolutePaths): void
    {
        if ($pdo instanceof \PDO) {
            foreach (array_values(array_unique($fileIds)) as $fileId) {
                $this->softDeleteFilemanagerById($pdo, (int) $fileId);
            }
        }

        foreach (array_values(array_unique($absolutePaths)) as $fullPath) {
            if ($fullPath !== '' && is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    private function softDeleteFilemanagerById(\PDO $pdo, int $fileId): void
    {
        if ($fileId <= 0) {
            return;
        }

        $stmt = $pdo->prepare('SELECT id, path FROM filemanager WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $fileId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return;
        }

        $delete = $pdo->prepare('UPDATE filemanager SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $delete->execute(['id' => $fileId]);

        $relative = ltrim((string) ($row['path'] ?? ''), '/');
        if ($relative === '' || !str_starts_with($relative, 'filemanager/')) {
            return;
        }

        $fullPath = app()->basePath('storage/' . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function sanitizeFileName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'file';
        }
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        if (!is_string($clean) || $clean === '') {
            return 'file';
        }

        return substr($clean, 0, 255);
    }

    private function tableExists(\PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function pickRelationLabelColumn(\PDO $pdo, string $table): string
    {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION ASC'
        );
        $stmt->execute(['table' => $table]);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return 'id';
        }

        $columns = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $col = strtolower(trim((string) ($row['COLUMN_NAME'] ?? '')));
            if ($col !== '') {
                $columns[] = $col;
            }
        }

        // Pass 1: exact match
        foreach (['nama', 'name', 'title', 'kode', 'code', 'label'] as $preferred) {
            if (in_array($preferred, $columns, true)) {
                return $preferred;
            }
        }

        // Pass 2: kolom yang mengandung kata nama/name (misal nama_barang, nama_jasa)
        foreach ($columns as $column) {
            if (str_contains($column, 'nama') || str_contains($column, 'name')) {
                return $column;
            }
        }

        // Pass 3: kolom non-id pertama
        foreach ($columns as $column) {
            if ($column !== 'id' && !str_ends_with($column, '_id')) {
                return $column;
            }
        }

        return 'id';
    }
}
