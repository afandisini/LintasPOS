<?php

declare(strict_types=1);

namespace App\Services;

class MenuGeneratorFileService
{
    /**
     * @param array<string, mixed> $generator
     * @param array<int, array<string, mixed>> $fields
     * @return array<int, array<string, mixed>>
     */
    public function generateCrudFiles(array $generator, array $fields): array
    {
        $controllerName = $this->sanitizeClassName((string) ($generator['controller_name'] ?? 'GeneratedController'));
        $viewFolder = $this->sanitizePathSegment((string) ($generator['view_folder'] ?? 'generated'));
        $moduleSlug = $this->sanitizePathSegment((string) ($generator['module_slug'] ?? 'generated'));
        $tableName = $this->sanitizePathSegment((string) ($generator['table_name'] ?? ''));
        $routePrefix = trim((string) ($generator['route_prefix'] ?? $moduleSlug), '/');
        $title = trim((string) ($generator['menu_title'] ?? ($generator['module_name'] ?? 'Modul')));

        $files = [];

        $controllerPath = app()->basePath('app/Controllers/' . $controllerName . '.php');
        $controllerContent = $this->buildControllerContent($controllerName, $viewFolder, $tableName, $routePrefix, $title);
        $files[] = $this->writeFile($controllerPath, $controllerContent, 'controller');

        $viewPath = app()->basePath('app/Views/' . $viewFolder . '/index.php');
        $viewContent = $this->buildViewContent($title, $moduleSlug, $routePrefix);
        $files[] = $this->writeFile($viewPath, $viewContent, 'view_index');

        $moduleDirName = $this->toPascalCase($moduleSlug);
        $scriptPath = app()->basePath('app/Modules/' . $moduleDirName . '/js/' . $moduleSlug . '.js');
        $scriptContent = $this->buildScriptContent();
        $files[] = $this->writeFile($scriptPath, $scriptContent, 'script');

        $routeSnippetPath = app()->basePath('storage/menu-generator/routes/' . $moduleSlug . '.php');
        $routeSnippetContent = $this->buildRouteSnippetContent($controllerName, $routePrefix);
        $files[] = $this->writeFile($routeSnippetPath, $routeSnippetContent, 'route');

        return $files;
    }

    private function sanitizeClassName(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_]/', '', $value);
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return 'GeneratedController';
        }

        if (!str_ends_with($value, 'Controller')) {
            $value .= 'Controller';
        }

        return $value;
    }

    private function sanitizePathSegment(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\\/-]/', '', $value);
        $value = is_string($value) ? trim($value, '/') : '';
        return $value !== '' ? $value : 'generated';
    }

    private function toPascalCase(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', strtolower(trim($value)));
        $value = ucwords($value);
        $value = str_replace(' ', '', $value);

        return $value !== '' ? $value : 'Generated';
    }

    /**
     * @return array<string, mixed>
     */
    private function writeFile(string $absolutePath, string $content, string $fileType): array
    {
        $directory = dirname($absolutePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($absolutePath, $content);
        $checksum = (string) sha1($content);
        $relativePath = $this->toRelativePath($absolutePath);

        return [
            'file_type' => $fileType,
            'file_path' => $relativePath,
            'file_name' => basename($absolutePath),
            'checksum_sha1' => $checksum,
            'is_generated' => 1,
        ];
    }

    private function toRelativePath(string $absolutePath): string
    {
        $basePath = str_replace('\\', '/', app()->basePath(''));
        $path = str_replace('\\', '/', $absolutePath);
        if (str_starts_with($path, $basePath . '/')) {
            return ltrim(substr($path, strlen($basePath)), '/');
        }

        return ltrim($path, '/');
    }

    private function buildControllerContent(
        string $controllerName,
        string $viewFolder,
        string $tableName,
        string $routePrefix,
        string $title
    ): string {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use System\Http\Request;
use System\Http\Response;
use Throwable;

class {$controllerName}
{
    public function index(Request \$request): Response
    {
        \$auth = is_array(\$_SESSION['auth'] ?? null) ? \$_SESSION['auth'] : [];
        \$rows = [];
        \$editableColumns = [];
        \$displayColumns = [];
        \$relations = [];
        \$totalRows = 0;

        try {
            \$pdo = Database::connection();
            \$editableColumns = \$this->editableColumns(\$pdo);
            \$relations = \$this->buildRelations(\$pdo, \$editableColumns);
            \$stmt = \$pdo->query('SELECT * FROM `{$tableName}` ORDER BY id DESC');
            \$rows = \$stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!is_array(\$rows)) {
                \$rows = [];
            }
            \$rows = \$this->decorateRowsWithRelations(\$rows, \$relations);
            \$totalRows = (int) \$pdo->query('SELECT COUNT(*) FROM `{$tableName}`')->fetchColumn();
            \$displayColumns = \$this->displayColumns(\$rows, \$editableColumns);
            \$rows = \$this->decorateRowsWithHelpers(\$pdo, \$rows, \$displayColumns);
        } catch (Throwable) {
            toast_add('Gagal memuat data {$title}.', 'error');
        }

        \$html = app()->view()->render('{$viewFolder}/index', [
            'title' => '{$title}',
            'auth' => \$auth,
            'activeMenu' => '{$routePrefix}',
            'rows' => \$rows,
            'totalRows' => \$totalRows,
            'editableColumns' => \$editableColumns,
            'displayColumns' => \$displayColumns,
            'relations' => \$relations,
            'routePrefix' => '{$routePrefix}',
        ]);

        return Response::html(\$html);
    }

    public function datatable(Request \$request): Response
    {
        try {
            \$pdo = Database::connection();
            \$editableColumns = \$this->editableColumns(\$pdo);
            \$displayColumns = \$this->displayColumns([], \$editableColumns);
            \$relations = \$this->buildRelations(\$pdo, \$editableColumns);
            \$params = \$request->all();

            \$draw = max(0, (int) (\$params['draw'] ?? 0));
            \$start = max(0, (int) (\$params['start'] ?? 0));
            \$length = (int) (\$params['length'] ?? 10);
            if (\$length < 1) {
                \$length = 10;
            }
            if (\$length > 100) {
                \$length = 100;
            }

            \$search = trim((string) ((\$params['search']['value'] ?? '') ?: ''));

            \$orderable = ['id'];
            foreach (\$displayColumns as \$column) {
                if (!is_array(\$column)) {
                    continue;
                }
                \$name = trim((string) (\$column['name'] ?? ''));
                if (\$name !== '' && preg_match('/^[a-zA-Z0-9_]+$/', \$name)) {
                    \$orderable[] = \$name;
                }
            }
            \$orderable = array_values(array_unique(\$orderable));
            \$orderMap = [0 => 'id'];
            \$displayOffset = 1;
            foreach (\$displayColumns as \$idx => \$column) {
                if (!is_array(\$column)) {
                    continue;
                }
                \$name = trim((string) (\$column['name'] ?? ''));
                if (\$name !== '' && preg_match('/^[a-zA-Z0-9_]+$/', \$name)) {
                    \$orderMap[\$displayOffset + (int) \$idx] = \$name;
                }
            }

            \$orderIndex = (int) (\$params['order'][0]['column'] ?? 0);
            \$orderColumn = \$orderMap[\$orderIndex] ?? 'id';
            \$orderDir = strtolower((string) (\$params['order'][0]['dir'] ?? 'desc'));
            \$orderDir = \$orderDir === 'asc' ? 'asc' : 'desc';

            \$whereSql = '';
            \$bindings = [];
            if (\$search !== '') {
                \$whereParts = [];
                foreach (\$orderable as \$idx => \$columnName) {
                    \$param = 'search_' . \$idx;
                    if (\$columnName === 'id') {
                        \$whereParts[] = 'CAST(`id` AS CHAR) LIKE :' . \$param;
                    } else {
                        \$whereParts[] = '`' . \$columnName . '` LIKE :' . \$param;
                    }
                    \$bindings[\$param] = '%' . \$search . '%';
                }
                if (\$whereParts !== []) {
                    \$whereSql = ' WHERE (' . implode(' OR ', \$whereParts) . ')';
                }
            }

            \$countTotal = (int) \$pdo->query('SELECT COUNT(*) FROM `{$tableName}`')->fetchColumn();
            if (\$whereSql === '') {
                \$countFiltered = \$countTotal;
            } else {
                \$countStmt = \$pdo->prepare('SELECT COUNT(*) FROM `{$tableName}`' . \$whereSql);
                foreach (\$bindings as \$key => \$value) {
                    \$countStmt->bindValue(':' . \$key, \$value);
                }
                \$countStmt->execute();
                \$countFiltered = (int) \$countStmt->fetchColumn();
            }

            \$sql = 'SELECT * FROM `{$tableName}`' . \$whereSql . ' ORDER BY `' . \$orderColumn . '` ' . \$orderDir . ' LIMIT :limit OFFSET :offset';
            \$stmt = \$pdo->prepare(\$sql);
            foreach (\$bindings as \$key => \$value) {
                \$stmt->bindValue(':' . \$key, \$value);
            }
            \$stmt->bindValue(':limit', \$length, \PDO::PARAM_INT);
            \$stmt->bindValue(':offset', \$start, \PDO::PARAM_INT);
            \$stmt->execute();
            \$rows = \$stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!is_array(\$rows)) {
                \$rows = [];
            }
            \$rows = \$this->decorateRowsWithRelations(\$rows, \$relations);
            \$rows = \$this->decorateRowsWithHelpers(\$pdo, \$rows, \$displayColumns);

            return Response::json([
                'draw' => \$draw,
                'recordsTotal' => \$countTotal,
                'recordsFiltered' => \$countFiltered,
                'data' => \$rows,
            ]);
        } catch (Throwable) {
            return Response::json([
                'draw' => max(0, (int) (\$request->all()['draw'] ?? 0)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Gagal memuat data {$title}.',
            ], 500);
        }
    }

    public function store(Request \$request): Response
    {
        try {
            \$pdo = Database::connection();
            \$columns = \$this->editableColumns(\$pdo);
            if (\$columns === []) {
                toast_add('Kolom input tidak tersedia.', 'warning');
                return Response::redirect('/{$routePrefix}');
            }

            \$insertColumns = [];
            \$params = [];
            \$missingLabels = [];
            foreach (\$columns as \$column) {
                if (!is_array(\$column)) {
                    continue;
                }
                \$name = (string) (\$column['name'] ?? '');
                \$label = (string) (\$column['label'] ?? \$name);
                \$inputType = (string) (\$column['input_type'] ?? 'text');
                \$required = (bool) (\$column['required'] ?? false);
                if (\$name === '') {
                    continue;
                }
                if (\$this->isAutoUpdateDateField(\$name)) {
                    \$value = \$inputType === 'datetime-local' ? date('Y-m-d H:i:s') : date('Y-m-d');
                } else {
                    \$value = trim((string) \$request->input(\$name, ''));
                }
                if (\$value === '' && \$required) {
                    if (\$inputType === 'date') {
                        \$value = date('Y-m-d');
                    } elseif (\$inputType === 'datetime-local') {
                        \$value = date('Y-m-d H:i:s');
                    }
                }
                if (\$value === '' && \$required) {
                    \$missingLabels[] = \$label !== '' ? \$label : \$name;
                    continue;
                }
                \$insertColumns[] = \$name;
                \$params[\$name] = \$value !== '' ? \$value : null;
            }

            if (\$missingLabels !== []) {
                toast_add('Field wajib belum terisi: ' . implode(', ', array_slice(\$missingLabels, 0, 3)), 'error');
                return Response::redirect('/{$routePrefix}');
            }

            if (\$insertColumns === []) {
                toast_add('Data input tidak valid.', 'error');
                return Response::redirect('/{$routePrefix}');
            }

            \$columnSql = '`' . implode('`,`', \$insertColumns) . '`';
            \$valueSql = ':' . implode(',:', \$insertColumns);
            \$stmt = \$pdo->prepare('INSERT INTO `{$tableName}` (' . \$columnSql . ') VALUES (' . \$valueSql . ')');
            \$stmt->execute(\$params);
            toast_add('Data {$title} berhasil ditambahkan.', 'success');
        } catch (Throwable) {
            toast_add('Gagal menambahkan data {$title}.', 'error');
        }

        return Response::redirect('/{$routePrefix}');
    }

    public function update(Request \$request, string \$id): Response
    {
        \$recordId = (int) \$id;
        if (\$recordId <= 0) {
            toast_add('ID data tidak valid.', 'error');
            return Response::redirect('/{$routePrefix}');
        }

        try {
            \$pdo = Database::connection();
            \$columns = \$this->editableColumns(\$pdo);
            if (\$columns === []) {
                toast_add('Kolom input tidak tersedia.', 'warning');
                return Response::redirect('/{$routePrefix}');
            }

            \$setParts = [];
            \$params = ['id' => \$recordId];
            \$missingLabels = [];
            foreach (\$columns as \$column) {
                if (!is_array(\$column)) {
                    continue;
                }
                \$name = (string) (\$column['name'] ?? '');
                \$label = (string) (\$column['label'] ?? \$name);
                \$inputType = (string) (\$column['input_type'] ?? 'text');
                \$required = (bool) (\$column['required'] ?? false);
                if (\$name === '') {
                    continue;
                }
                if (\$this->isAutoUpdateDateField(\$name)) {
                    \$value = \$inputType === 'datetime-local' ? date('Y-m-d H:i:s') : date('Y-m-d');
                } else {
                    \$value = trim((string) \$request->input(\$name, ''));
                }
                if (\$value === '' && \$required) {
                    if (\$inputType === 'date') {
                        \$value = date('Y-m-d');
                    } elseif (\$inputType === 'datetime-local') {
                        \$value = date('Y-m-d H:i:s');
                    }
                }
                if (\$value === '' && \$required) {
                    \$missingLabels[] = \$label !== '' ? \$label : \$name;
                    continue;
                }
                \$setParts[] = '`' . \$name . '` = :' . \$name;
                \$params[\$name] = \$value !== '' ? \$value : null;
            }

            if (\$missingLabels !== []) {
                toast_add('Field wajib belum terisi: ' . implode(', ', array_slice(\$missingLabels, 0, 3)), 'error');
                return Response::redirect('/{$routePrefix}');
            }

            if (\$setParts === []) {
                toast_add('Data input tidak valid.', 'error');
                return Response::redirect('/{$routePrefix}');
            }

            \$sql = 'UPDATE `{$tableName}` SET ' . implode(', ', \$setParts) . ' WHERE id = :id';
            \$stmt = \$pdo->prepare(\$sql);
            \$stmt->execute(\$params);
            toast_add('Data {$title} berhasil diperbarui.', 'success');
        } catch (Throwable) {
            toast_add('Gagal memperbarui data {$title}.', 'error');
        }

        return Response::redirect('/{$routePrefix}');
    }

    public function destroy(Request \$request, string \$id): Response
    {
        \$recordId = (int) \$id;
        if (\$recordId <= 0) {
            toast_add('ID data tidak valid.', 'error');
            return Response::redirect('/{$routePrefix}');
        }

        try {
            \$pdo = Database::connection();
            \$stmt = \$pdo->prepare('DELETE FROM `{$tableName}` WHERE id = :id');
            \$stmt->execute(['id' => \$recordId]);
            toast_add('Data {$title} berhasil dihapus.', 'success');
        } catch (Throwable) {
            toast_add('Gagal menghapus data {$title}.', 'error');
        }

        return Response::redirect('/{$routePrefix}');
    }

    /**
     * @return array<int, array{name: string, label: string, input_type: string, required: bool, relation_table: string|null}>
     */
    private function isAutoUpdateDateField(string \$fieldName): bool
    {
        \$normalized = strtolower(trim(\$fieldName));
        return in_array(\$normalized, ['tgl_update', 'tangal_updata'], true);
    }

    /**
     * @return array<int, array{name: string, label: string, input_type: string, required: bool, relation_table: string|null}>
     */
    private function editableColumns(\PDO \$pdo): array
    {
        \$rows = [];
        try {
            \$stmt = \$pdo->prepare(
                'SELECT COLUMN_NAME, DATA_TYPE '
                . 'FROM INFORMATION_SCHEMA.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table '
                . 'ORDER BY ORDINAL_POSITION ASC'
            );
            \$stmt->execute(['table' => '{$tableName}']);
            \$infoRows = \$stmt->fetchAll();
            if (is_array(\$infoRows)) {
                \$rows = \$infoRows;
            }
        } catch (Throwable) {
            \$rows = [];
        }

        if (\$rows === []) {
            try {
                \$descRows = \$pdo->query('DESCRIBE `{$tableName}`')->fetchAll();
                if (is_array(\$descRows)) {
                    foreach (\$descRows as \$descRow) {
                        if (!is_array(\$descRow)) {
                            continue;
                        }
                        \$rows[] = [
                            'COLUMN_NAME' => (string) (\$descRow['Field'] ?? ''),
                            'DATA_TYPE' => strtolower((string) (\$descRow['Type'] ?? 'varchar')),
                            'IS_NULLABLE' => strtoupper((string) (\$descRow['Null'] ?? 'YES')) === 'YES' ? 'YES' : 'NO',
                            'COLUMN_DEFAULT' => \$descRow['Default'] ?? null,
                        ];
                    }
                }
            } catch (Throwable) {
                \$rows = [];
            }
        }

        if (\$rows === []) {
            return [];
        }

        \$result = [];
        foreach (\$rows as \$row) {
            if (!is_array(\$row)) {
                continue;
            }
            \$name = strtolower(trim((string) (\$row['COLUMN_NAME'] ?? '')));
            if (\$name === '' || in_array(\$name, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            \$dataType = strtolower((string) (\$row['DATA_TYPE'] ?? 'varchar'));
            if (str_contains(\$dataType, '(')) {
                \$dataType = (string) strstr(\$dataType, '(', true);
            }
            \$nullable = strtoupper((string) (\$row['IS_NULLABLE'] ?? 'YES')) === 'YES';
            \$defaultValue = \$row['COLUMN_DEFAULT'] ?? null;
            \$required = !\$nullable && \$defaultValue === null;
            \$inputType = 'text';
            \$relationTable = null;
            if (str_ends_with(\$name, '_id') && \$name !== 'id') {
                \$inputType = 'select';
                \$relationTable = substr(\$name, 0, -3) ?: null;
            } elseif (str_ends_with(\$name, '_textarea') || in_array(\$dataType, ['text', 'longtext', 'mediumtext'], true)) {
                \$inputType = 'textarea';
            } elseif (in_array(\$dataType, ['date'], true)) {
                \$inputType = 'date';
            } elseif (in_array(\$dataType, ['datetime', 'timestamp'], true)) {
                \$inputType = 'datetime-local';
            } elseif (in_array(\$dataType, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'double', 'float'], true)) {
                \$inputType = 'number';
            }

            \$result[] = [
                'name' => \$name,
                'label' => ucwords(str_replace('_', ' ', preg_replace('/(_id|_img|_file|_textarea)$/', '', \$name) ?: \$name)),
                'input_type' => \$inputType,
                'required' => \$required,
                'relation_table' => \$relationTable,
            ];
        }

        return \$result;
    }

    /**
     * @param array<int, array<string, mixed>> \$rows
     * @param array<int, array{name: string, label: string, input_type: string, required: bool, relation_table: string|null}> \$editableColumns
     * @return array<int, array{name: string, label: string}>
     */
    private function displayColumns(array \$rows, array \$editableColumns): array
    {
        \$picked = [];
        foreach (\$editableColumns as \$column) {
            if (!is_array(\$column)) {
                continue;
            }
            \$name = (string) (\$column['name'] ?? '');
            if (\$name === '') {
                continue;
            }
            \$picked[] = [
                'name' => \$name,
                'label' => (string) (\$column['label'] ?? \$name),
            ];
        }

        if (\$picked !== []) {
            return \$picked;
        }

        if (\$rows !== [] && is_array(\$rows[0])) {
            foreach (array_keys(\$rows[0]) as \$key) {
                \$name = (string) \$key;
                if (
                    \$name === ''
                    || ctype_digit(\$name)
                    || in_array(\$name, ['id', 'created_at', 'updated_at', 'deleted_at'], true)
                ) {
                    continue;
                }
                \$picked[] = [
                    'name' => \$name,
                    'label' => ucwords(str_replace('_', ' ', \$name)),
                ];
            }
        }

        return \$picked;
    }

    /**
     * @param array<int, array{name: string, label: string, input_type: string, required: bool, relation_table: string|null}> \$editableColumns
     * @return array<string, array{table: string, key: string, label: string, options: array<int, array{id: string, label: string}>}>
     */
    private function buildRelations(\PDO \$pdo, array \$editableColumns): array
    {
        \$relations = [];
        foreach (\$editableColumns as \$column) {
            if (!is_array(\$column)) {
                continue;
            }
            \$name = trim((string) (\$column['name'] ?? ''));
            \$inputType = (string) (\$column['input_type'] ?? 'text');
            \$table = trim((string) (\$column['relation_table'] ?? ''));
            if (\$name === '' || \$inputType !== 'select' || \$table === '') {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', \$table)) {
                continue;
            }
            if (!\$this->tableExists(\$pdo, \$table)) {
                continue;
            }

            \$labelColumn = \$this->pickRelationLabelColumn(\$pdo, \$table);
            \$stmt = \$pdo->query('SELECT `id`, `' . \$labelColumn . '` AS `label` FROM `' . \$table . '` ORDER BY `id` ASC');
            \$rows = \$stmt->fetchAll(\PDO::FETCH_ASSOC);
            \$options = [];
            if (is_array(\$rows)) {
                foreach (\$rows as \$row) {
                    if (!is_array(\$row)) {
                        continue;
                    }
                    \$options[] = [
                        'id' => (string) (\$row['id'] ?? ''),
                        'label' => (string) (\$row['label'] ?? ''),
                    ];
                }
            }

            \$relations[\$name] = [
                'table' => \$table,
                'key' => 'id',
                'label' => \$labelColumn,
                'options' => \$options,
            ];
        }

        return \$relations;
    }

    /**
     * @param array<int, array<string, mixed>> \$rows
     * @param array<string, array{table: string, key: string, label: string, options: array<int, array{id: string, label: string}>}> \$relations
     * @return array<int, array<string, mixed>>
     */
    private function decorateRowsWithRelations(array \$rows, array \$relations): array
    {
        if (\$rows === [] || \$relations === []) {
            return \$rows;
        }

        \$maps = [];
        foreach (\$relations as \$field => \$meta) {
            \$map = [];
            \$options = \$meta['options'] ?? [];
            if (is_array(\$options)) {
                foreach (\$options as \$option) {
                    if (!is_array(\$option)) {
                        continue;
                    }
                    \$id = (string) (\$option['id'] ?? '');
                    \$label = (string) (\$option['label'] ?? '');
                    if (\$id !== '') {
                        \$map[\$id] = \$label !== '' ? \$label : \$id;
                    }
                }
            }
            \$maps[\$field] = \$map;
        }

        foreach (\$rows as \$index => \$row) {
            if (!is_array(\$row)) {
                continue;
            }
            foreach (\$maps as \$field => \$map) {
                \$raw = (string) (\$row[\$field] ?? '');
                if (\$raw === '') {
                    continue;
                }
                \$rows[\$index][\$field . '__label'] = (string) (\$map[\$raw] ?? \$raw);
            }
        }

        return \$rows;
    }

    /**
     * @param array<int, array<string, mixed>> \$rows
     * @param array<int, array{name: string, label: string}> \$displayColumns
     * @return array<int, array<string, mixed>>
     */
    private function decorateRowsWithHelpers(\PDO \$pdo, array \$rows, array \$displayColumns): array
    {
        if (\$rows === [] || \$displayColumns === []) {
            return \$rows;
        }

        \$imageColumns = \$this->detectImageColumns(\$displayColumns);
        \$currencyColumns = \$this->detectCurrencyColumns(\$displayColumns);
        \$filemanagerPathMap = \$this->buildFilemanagerPathMap(\$pdo, \$rows, \$imageColumns);

        foreach (\$rows as \$index => \$row) {
            if (!is_array(\$row)) {
                continue;
            }

            foreach (\$currencyColumns as \$column) {
                \$rawValue = \$row[\$column] ?? null;
                \$rows[\$index][\$column . '__display'] = format_currency_id(\$rawValue);
            }

            foreach (\$imageColumns as \$column) {
                \$rawValue = trim((string) (\$row[\$column] ?? ''));
                \$resolvedValue = \$rawValue;
                if (\$rawValue !== '' && ctype_digit(\$rawValue)) {
                    \$resolvedValue = (string) (\$filemanagerPathMap[(int) \$rawValue] ?? \$rawValue);
                }
                \$rows[\$index][\$column . '__html'] = render_image_cell(\$resolvedValue, (string) (\$column));
                \$rows[\$index][\$column . '__display'] = \$resolvedValue !== '' ? \$resolvedValue : '-';
            }
        }

        return \$rows;
    }

    /**
     * @param array<int, array{name: string, label: string}> \$displayColumns
     * @return array<int, string>
     */
    private function detectImageColumns(array \$displayColumns): array
    {
        \$result = [];
        foreach (\$displayColumns as \$column) {
            if (!is_array(\$column)) {
                continue;
            }
            \$name = strtolower(trim((string) (\$column['name'] ?? '')));
            if (\$name === '') {
                continue;
            }
            if (preg_match('/(^|_)(gambar|image|img|foto|photo|avatar|logo|thumbnail|thumb|cover|icon|banner)($|_)/', \$name)) {
                \$result[] = \$name;
            }
        }

        return array_values(array_unique(\$result));
    }

    /**
     * @param array<int, array{name: string, label: string}> \$displayColumns
     * @return array<int, string>
     */
    private function detectCurrencyColumns(array \$displayColumns): array
    {
        \$result = [];
        foreach (\$displayColumns as \$column) {
            if (!is_array(\$column)) {
                continue;
            }
            \$name = strtolower(trim((string) (\$column['name'] ?? '')));
            if (\$name === '') {
                continue;
            }
            if (preg_match('/(^|_)(harga|price|amount|nominal|total|biaya|bayar|subtotal|saldo)($|_)/', \$name)) {
                \$result[] = \$name;
            }
        }

        return array_values(array_unique(\$result));
    }

    /**
     * @param array<int, array<string, mixed>> \$rows
     * @param array<int, string> \$imageColumns
     * @return array<int, string>
     */
    private function buildFilemanagerPathMap(\PDO \$pdo, array \$rows, array \$imageColumns): array
    {
        if (\$rows === [] || \$imageColumns === []) {
            return [];
        }

        \$fileIds = [];
        foreach (\$rows as \$row) {
            if (!is_array(\$row)) {
                continue;
            }
            foreach (\$imageColumns as \$column) {
                \$raw = trim((string) (\$row[\$column] ?? ''));
                if (\$raw !== '' && ctype_digit(\$raw)) {
                    \$id = (int) \$raw;
                    if (\$id > 0) {
                        \$fileIds[\$id] = true;
                    }
                }
            }
        }

        if (\$fileIds === []) {
            return [];
        }

        \$idList = array_keys(\$fileIds);
        \$placeholders = implode(',', array_fill(0, count(\$idList), '?'));
        \$stmt = \$pdo->prepare('SELECT id, path FROM filemanager WHERE deleted_at IS NULL AND id IN (' . \$placeholders . ')');
        \$stmt->execute(\$idList);
        \$map = [];
        \$rowsDb = \$stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array(\$rowsDb)) {
            return [];
        }
        foreach (\$rowsDb as \$rowDb) {
            if (!is_array(\$rowDb)) {
                continue;
            }
            \$id = (int) (\$rowDb['id'] ?? 0);
            \$path = ltrim((string) (\$rowDb['path'] ?? ''), '/');
            if (\$id > 0 && \$path !== '' && str_starts_with(\$path, 'filemanager/')) {
                \$map[\$id] = \$path;
            }
        }

        return \$map;
    }

    private function tableExists(\PDO \$pdo, string \$table): bool
    {
        \$stmt = \$pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        \$stmt->execute(['table' => \$table]);
        return (int) \$stmt->fetchColumn() > 0;
    }

    private function pickRelationLabelColumn(\PDO \$pdo, string \$table): string
    {
        \$stmt = \$pdo->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION ASC'
        );
        \$stmt->execute(['table' => \$table]);
        \$rows = \$stmt->fetchAll();
        if (!is_array(\$rows)) {
            return 'id';
        }

        \$columns = [];
        foreach (\$rows as \$row) {
            if (!is_array(\$row)) {
                continue;
            }
            \$col = strtolower(trim((string) (\$row['COLUMN_NAME'] ?? '')));
            if (\$col !== '') {
                \$columns[] = \$col;
            }
        }

        foreach (['nama', 'name', 'title', 'kode', 'code', 'label'] as \$preferred) {
            if (in_array(\$preferred, \$columns, true)) {
                return \$preferred;
            }
        }

        foreach (\$columns as \$column) {
            if (\$column !== 'id' && !str_ends_with(\$column, '_id')) {
                return \$column;
            }
        }

        return 'id';
    }
}

PHP;
    }

    private function buildViewContent(string $title, string $moduleSlug, string $routePrefix): string
    {
        $moduleDir = $this->toPascalCase($moduleSlug);
        return <<<PHP
<?php
/** @var string \$title */
/** @var array<string,mixed> \$auth */
/** @var string \$activeMenu */
/** @var array<int,array<string,mixed>> \$rows */
/** @var int \$totalRows */
/** @var array<int,array{name: string, label: string, input_type: string, required: bool, relation_table: string|null}> \$editableColumns */
/** @var array<int,array{name: string, label: string}> \$displayColumns */
/** @var array<string, array{table: string, key: string, label: string, options: array<int, array{id: string, label: string}>}> \$relations */
/** @var string \$routePrefix */
?>
<?php
\$manualDisplayFieldNames = [];
\$displayColumnsByName = [];
foreach ((array) \$displayColumns as \$column) {
    if (!is_array(\$column)) {
        continue;
    }
    \$name = trim((string) (\$column['name'] ?? ''));
    if (\$name === '') {
        continue;
    }
    \$displayColumnsByName[\$name] = [
        'name' => \$name,
        'label' => (string) (\$column['label'] ?? \$name),
        'class' => '',
    ];
}

\$tableColumns = [];
if (\$manualDisplayFieldNames !== []) {
    \$parsedManualFields = parseFieldDefinitions(\$manualDisplayFieldNames);
    foreach (\$parsedManualFields as \$idx => \$parsedField) {
        \$name = trim((string) (\$parsedField['name'] ?? ''));
        if (\$name === '') {
            continue;
        }

        \$rawDefinition = (string) (\$manualDisplayFieldNames[\$idx] ?? \$name);
        \$hasBracketMeta = str_contains(\$rawDefinition, '[');
        \$defaultLabel = field_label_from_name(\$name);
        \$parsedLabel = (string) (\$parsedField['label'] ?? \$defaultLabel);
        \$resolvedLabel = \$parsedLabel;
        if (!\$hasBracketMeta && isset(\$displayColumnsByName[\$name])) {
            \$resolvedLabel = (string) (\$displayColumnsByName[\$name]['label'] ?? \$parsedLabel);
        }

        \$tableColumns[] = [
            'name' => \$name,
            'label' => \$resolvedLabel !== '' ? \$resolvedLabel : \$defaultLabel,
            'class' => (string) (\$parsedField['class'] ?? ''),
        ];
    }
} else {
    \$tableColumns = array_values(\$displayColumnsByName);
}

\$displayColumnsJs = [];
foreach (\$tableColumns as \$column) {
    if (!is_array(\$column)) {
        continue;
    }
    \$displayColumnsJs[] = [
        'name' => (string) (\$column['name'] ?? ''),
        'label' => (string) (\$column['label'] ?? ''),
    ];
}
\$routePrefixJs = (string) \$routePrefix;

\$dataTablesHead = raw(
    '<link href="' . e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.css')) . '" rel="stylesheet">'
        . '<style>'
        . '.generated-detail-scroll{max-height:65vh;overflow-y:auto;padding-right:6px;}'
        . '</style>'
);
?>
<?= raw(view('partials/dashboard/head', ['title' => \$title ?? '{$title}', 'extraHead' => \$dataTablesHead])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => \$auth, 'activeMenu' => \$activeMenu ?? '{$routePrefix}'])) ?>
<main class="main" id="mainContent">
    <div class="pg-header mb-3 anim">
        <h1><?= e(\$title ?? '{$title}') ?></h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <p class="small">Fitur untuk Mengelola <?= e(\$title ?? '{$title}') ?></p>
            </div>
            <div>
                <?= raw(view('partials/dashboard/breadcrumb', [
                    'items' => [
                        ['label' => 'Dashboard', 'url' => site_url('dashboard')],
                    ],
                    'current' => \$title ?? '{$title}',
                ])) ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 anim">
            <div class="panel">
                <div class="panel-head">
                    <span class="panel-title">Daftar <?= e(\$title ?? '{$title}') ?></span>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn-a" data-cm-open="cmAddGenerated">
                            <i class="bi bi-plus-circle"></i><span>Tambah <?= e(\$title ?? '{$title}') ?></span>
                        </button>
                        <span class="text-muted small">Total: <?= e((string) (\$totalRows ?? count(\$rows))) ?></span>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="dt-wrap generated-dt-wrap">
                        <table class="dtable generated-table w-100 nowrap" id="generatedTable">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <?php foreach (\$tableColumns as \$column): ?>
                                        <?php \$columnClass = trim((string) (\$column['class'] ?? '')); ?>
                                        <th<?= \$columnClass !== '' ? ' class="' . e(\$columnClass) . '"' : '' ?>><?= e((string) (\$column['label'] ?? '-')) ?></th>
                                    <?php endforeach; ?>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="<?= e((string) (count(\$tableColumns) + 2)) ?>" class="text-muted">Memuat data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="cm-bg" id="cmAddGenerated" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmAddGeneratedTitle">
        <form method="post" action="<?= e(site_url(\$routePrefix)) ?>" autocomplete="off">
            <?= raw(csrf_field()) ?>
            <div class="panel-head">
                <span class="panel-title" id="cmAddGeneratedTitle">Tambah <?= e(\$title ?? '{$title}') ?></span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                <div class="u-form-grid">
                    <?php foreach (\$editableColumns as \$column): ?>
                        <?php
                        \$name = (string) (\$column['name'] ?? '');
                        \$label = (string) (\$column['label'] ?? \$name);
                        \$inputType = (string) (\$column['input_type'] ?? 'text');
                        \$required = (bool) (\$column['required'] ?? false);
                        \$relationMeta = is_array(\$relations[\$name] ?? null) ? \$relations[\$name] : null;
                        \$relationOptions = is_array(\$relationMeta['options'] ?? null) ? \$relationMeta['options'] : [];
                        ?>
                        <div>
                            <label class="u-label"><?= e(\$label) ?><?= \$required ? ' *' : '' ?></label>
                            <?php if (\$inputType === 'textarea'): ?>
                                <textarea class="u-input" name="<?= e(\$name) ?>" rows="3" placeholder="Masukkan <?= e(strtolower(\$label)) ?>" <?= \$required ? 'required' : '' ?>></textarea>
                            <?php elseif (\$inputType === 'select'): ?>
                                <select class="u-input" name="<?= e(\$name) ?>" <?= \$required ? 'required' : '' ?>>
                                    <option value="">Pilih <?= e(\$label) ?></option>
                                    <?php foreach (\$relationOptions as \$option): ?>
                                        <?php \$optionId = (string) (\$option['id'] ?? ''); ?>
                                        <?php \$optionLabel = (string) (\$option['label'] ?? \$optionId); ?>
                                        <option value="<?= e(\$optionId) ?>"><?= e(\$optionLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input class="u-input" type="<?= e(\$inputType) ?>" name="<?= e(\$name) ?>" placeholder="Masukkan <?= e(strtolower(\$label)) ?>" <?= \$required ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="submit" class="btn-a">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="cm-bg" id="cmEditGenerated" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmEditGeneratedTitle">
        <form method="post" id="formEditGenerated" action="<?= e(site_url(\$routePrefix . '/0/update')) ?>" autocomplete="off">
            <?= raw(csrf_field()) ?>
            <div class="panel-head">
                <span class="panel-title" id="cmEditGeneratedTitle">Edit <?= e(\$title ?? '{$title}') ?></span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                <div class="u-form-grid">
                    <?php foreach (\$editableColumns as \$column): ?>
                        <?php
                        \$name = (string) (\$column['name'] ?? '');
                        \$label = (string) (\$column['label'] ?? \$name);
                        \$inputType = (string) (\$column['input_type'] ?? 'text');
                        \$required = (bool) (\$column['required'] ?? false);
                        \$relationMeta = is_array(\$relations[\$name] ?? null) ? \$relations[\$name] : null;
                        \$relationOptions = is_array(\$relationMeta['options'] ?? null) ? \$relationMeta['options'] : [];
                        ?>
                        <div>
                            <label class="u-label"><?= e(\$label) ?><?= \$required ? ' *' : '' ?></label>
                            <?php if (\$inputType === 'textarea'): ?>
                                <textarea class="u-input" id="edit_<?= e(\$name) ?>" name="<?= e(\$name) ?>" rows="3" <?= \$required ? 'required' : '' ?>></textarea>
                            <?php elseif (\$inputType === 'select'): ?>
                                <select class="u-input" id="edit_<?= e(\$name) ?>" name="<?= e(\$name) ?>" <?= \$required ? 'required' : '' ?>>
                                    <option value="">Pilih <?= e(\$label) ?></option>
                                    <?php foreach (\$relationOptions as \$option): ?>
                                        <?php \$optionId = (string) (\$option['id'] ?? ''); ?>
                                        <?php \$optionLabel = (string) (\$option['label'] ?? \$optionId); ?>
                                        <option value="<?= e(\$optionId) ?>"><?= e(\$optionLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input class="u-input" type="<?= e(\$inputType) ?>" id="edit_<?= e(\$name) ?>" name="<?= e(\$name) ?>" <?= \$required ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="submit" class="btn-a">Update</button>
            </div>
        </form>
    </div>
</div>

<div class="cm-bg" id="cmDeleteGenerated" data-cm-bg>
    <div class="panel cm-box" role="dialog" aria-modal="true" aria-labelledby="cmDeleteGeneratedTitle">
        <form method="post" id="formDeleteGenerated" action="<?= e(site_url(\$routePrefix . '/0/delete')) ?>">
            <?= raw(csrf_field()) ?>
            <div class="panel-head">
                <span class="panel-title" id="cmDeleteGeneratedTitle">Konfirmasi Hapus</span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                Hapus data <strong id="delete_generated_label">-</strong>? Tindakan ini tidak bisa dibatalkan.
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="submit" class="btn-a">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<div class="cm-bg" id="cmDetailGenerated" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmDetailGeneratedTitle">
        <div class="panel-head">
            <span class="panel-title" id="cmDetailGeneratedTitle">Detail Data</span>
            <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="panel-body generated-detail-scroll" id="generated_detail_content">
            <div class="text-muted">Data tidak tersedia.</div>
        </div>
        <div class="cm-foot">
            <button type="button" class="btn-g" data-cm-close>Tutup</button>
        </div>
    </div>
</div>

<?= raw(view('partials/shared/toast')) ?>
<?= raw(helper_toast_script()) ?>
<?= raw(module_script('Dashboard/js/dashboard.js')) ?>
<script src="<?= e(base_url('assets/vendor/jquery/jquery-3.7.1.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/vendor/datatables/jquery.dataTables.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.js')) ?>"></script>
<script>
window.generatedCrudConfig = {
    datatableUrl: <?= json_encode(site_url(\$routePrefix . '/datatable'), JSON_UNESCAPED_UNICODE) ?>,
    languageUrl: <?= json_encode(base_url('assets/vendor/datatables/id.json'), JSON_UNESCAPED_UNICODE) ?>,
    displayColumns: <?= json_encode(\$displayColumnsJs, JSON_UNESCAPED_UNICODE) ?>,
    routePrefix: <?= json_encode(\$routePrefixJs, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<?= raw(module_script('{$moduleDir}/js/{$moduleSlug}.js')) ?>
<?= raw(view('partials/dashboard/shell_close')) ?>

PHP;
    }

    private function buildScriptContent(): string
    {
        return <<<JS
(function () {
    var rowCache = {};

    function openModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(element) {
        var modal = element instanceof HTMLElement && element.classList.contains('cm-bg')
            ? element
            : (element instanceof HTMLElement ? element.closest('.cm-bg') : null);
        if (!modal) return;
        modal.classList.remove('show');
        if (!document.querySelector('.cm-bg.show')) {
            document.body.style.overflow = '';
        }
    }

    function closeAllModals() {
        document.querySelectorAll('.cm-bg.show').forEach(function (m) {
            m.classList.remove('show');
        });
        document.body.style.overflow = '';
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getInitials(value) {
        var text = String(value == null ? '' : value).trim();
        if (text === '') return '?';
        var parts = text.split(/\\s+/).filter(Boolean);
        var picked = [];
        if (parts.length > 0) picked.push(parts[0].charAt(0));
        if (parts.length > 1) picked.push(parts[parts.length - 1].charAt(0));
        var initials = picked.join('').toUpperCase();
        if (initials !== '') return initials;
        return text.charAt(0).toUpperCase();
    }

    function stripTags(value) {
        return String(value == null ? '' : value).replace(/<[^>]*>/g, '').trim();
    }

    function looksLikeImageFieldName(name) {
        return /(gambar|image|img|foto|photo|thumbnail|thumb|avatar|logo)/i.test(String(name || ''));
    }

    function looksLikeImageUrl(url) {
        var text = String(url == null ? '' : url).trim();
        if (text === '') return false;
        if (/^data:image\\//i.test(text)) return true;
        return /\\.(png|jpe?g|gif|webp|svg|bmp|ico)(\\?.*)?$/i.test(text);
    }

    function getRowDisplayName(row) {
        var preferredKeys = ['nama', 'name', 'nama_barang', 'judul', 'title', 'kode', 'kode_barang', 'id'];
        for (var i = 0; i < preferredKeys.length; i += 1) {
            var candidate = safeString(getDisplayValue(row, preferredKeys[i]));
            if (candidate !== '-') return stripTags(candidate);
        }
        var keys = Object.keys(row || {});
        for (var j = 0; j < keys.length; j += 1) {
            var raw = normalizeCellValue(row[keys[j]]);
            var text = String(raw == null ? '' : raw).trim();
            if (text !== '') return stripTags(text);
        }
        return 'Tanpa Nama';
    }

    function extractImageUrlFromHtml(html) {
        var text = String(html == null ? '' : html).trim();
        if (text === '') return '';
        var match = text.match(/<img[^>]+src\\s*=\\s*["']?([^"' >]+)["']?[^>]*>/i);
        return match && match[1] ? String(match[1]).trim() : '';
    }

    function createImageFallbackHtml(imageUrl, initials, alt, cssClass) {
        var safeInitials = escapeHtml(initials || '?');
        var safeAlt = escapeHtml(alt || 'Gambar');
        var fallback = '<span class="generated-avatar-fallback" aria-hidden="true">' + safeInitials + '</span>';
        if (String(imageUrl || '').trim() === '') {
            return '<div class="' + cssClass + '">' + fallback + '</div>';
        }
        return '<div class="' + cssClass + '">' +
            '<img src="' + escapeHtml(imageUrl) + '" alt="' + safeAlt + '" onerror="this.remove();">' +
            fallback +
            '</div>';
    }

    function resolveImagePreview(row, cfg) {
        var displayName = getRowDisplayName(row);
        var initials = getInitials(displayName);
        var displayColumns = Array.isArray(cfg.displayColumns) ? cfg.displayColumns : [];

        for (var i = 0; i < displayColumns.length; i += 1) {
            var fieldName = String((displayColumns[i] && displayColumns[i].name) || '').trim();
            if (fieldName === '' || !looksLikeImageFieldName(fieldName)) continue;

            var helperHtml = getDisplayHtml(row, fieldName);
            var imageFromHtml = extractImageUrlFromHtml(helperHtml);
            if (imageFromHtml !== '') {
                return { url: imageFromHtml, initials: initials, alt: displayName };
            }

            var rawValue = normalizeCellValue(getDisplayValue(row, fieldName));
            if (looksLikeImageUrl(rawValue)) {
                return { url: String(rawValue).trim(), initials: initials, alt: displayName };
            }
        }

        var keys = Object.keys(row || {});
        for (var j = 0; j < keys.length; j += 1) {
            var key = String(keys[j] || '');
            if (!looksLikeImageFieldName(key)) continue;
            var directRaw = normalizeCellValue(row[key]);
            if (looksLikeImageUrl(directRaw)) {
                return { url: String(directRaw).trim(), initials: initials, alt: displayName };
            }
        }

        return { url: '', initials: initials, alt: displayName };
    }

    function safeString(value) {
        var normalized = normalizeCellValue(value);
        var text = normalized == null ? '' : String(normalized);
        return text !== '' ? text : '-';
    }

    function normalizeCellValue(value) {
        if (value == null) return '';
        if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
            return value;
        }
        if (Array.isArray(value)) {
            return value.map(function (item) { return normalizeCellValue(item); }).join(', ');
        }
        if (typeof value === 'object') {
            if (Object.prototype.hasOwnProperty.call(value, 'display')) return value.display;
            if (Object.prototype.hasOwnProperty.call(value, 'label')) return value.label;
            if (Object.prototype.hasOwnProperty.call(value, 'value')) return value.value;
            if (Object.prototype.hasOwnProperty.call(value, 'text')) return value.text;
            return '';
        }
        return '';
    }

    function pickRowValue(row, key, fallback) {
        if (!row || typeof row !== 'object') return fallback;
        if (Object.prototype.hasOwnProperty.call(row, key)) return row[key];
        var lower = String(key || '').toLowerCase();
        if (lower !== '' && Object.prototype.hasOwnProperty.call(row, lower)) return row[lower];
        var upper = String(key || '').toUpperCase();
        if (upper !== '' && Object.prototype.hasOwnProperty.call(row, upper)) return row[upper];
        return fallback;
    }

    function getDisplayValue(row, fieldName) {
        var helperDisplayKey = String(fieldName || '') + '__display';
        var helperDisplayValue = pickRowValue(row, helperDisplayKey, null);
        if (normalizeCellValue(helperDisplayValue) !== '') {
            return helperDisplayValue;
        }
        var relationLabelKey = String(fieldName || '') + '__label';
        var relationValue = pickRowValue(row, relationLabelKey, null);
        if (normalizeCellValue(relationValue) !== '') {
            return relationValue;
        }
        return pickRowValue(row, fieldName, '');
    }

    function getDisplayHtml(row, fieldName) {
        var helperHtmlKey = String(fieldName || '') + '__html';
        var helperHtmlValue = pickRowValue(row, helperHtmlKey, '');
        var html = String(helperHtmlValue == null ? '' : helperHtmlValue).trim();
        return html !== '' ? html : '';
    }

    function buildActionButtons(row, cfg) {
        var id = Number(row && row.id ? row.id : 0);
        if (!Number.isFinite(id) || id <= 0) {
            return '-';
        }
        rowCache[id] = row || {};

        var firstColumnName = '';
        if (Array.isArray(cfg.displayColumns) && cfg.displayColumns.length > 0) {
            firstColumnName = String(cfg.displayColumns[0].name || '');
        }
        var label = firstColumnName !== '' ? safeString(getDisplayValue(row, firstColumnName)) : ('ID ' + id);
        var updateUrl = '/' + cfg.routePrefix + '/' + id + '/update';
        var deleteUrl = '/' + cfg.routePrefix + '/' + id + '/delete';

        return '' +
            '<div class="d-flex gap-2">' +
            '<button type="button" class="btn-g btn-sm btn-generated-detail" data-id="' + id + '" title="Detail">' +
            '<i class="bi bi-eye"></i></button>' +
            '<button type="button" class="btn-g btn-sm btn-generated-edit" data-id="' + id + '" data-action="' + escapeHtml(updateUrl) + '">' +
            '<i class="bi bi-pencil-square"></i></button>' +
            '<button type="button" class="btn-a btn-sm btn-generated-delete" data-id="' + id + '" data-label="' + escapeHtml(label) + '" data-action="' + escapeHtml(deleteUrl) + '">' +
            '<i class="bi bi-trash3"></i></button>' +
            '</div>';
    }

    function initDatatable() {
        var cfg = window.generatedCrudConfig || null;
        if (!cfg || !cfg.datatableUrl) return;
        if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.DataTable === 'undefined') return;

        var columns = [
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (data, type, row, meta) {
                    var start = meta && meta.settings && meta.settings._iDisplayStart ? meta.settings._iDisplayStart : 0;
                    return start + meta.row + 1;
                }
            }
        ];

        var displayColumns = Array.isArray(cfg.displayColumns) ? cfg.displayColumns : [];
        displayColumns.forEach(function (column) {
            let name = String((column && column.name) || '').trim();
            if (name === '') return;
            columns.push({
                data: null,
                defaultContent: '',
                render: function (data, type, row) {
                    var helperHtml = getDisplayHtml(row, name);
                    if (helperHtml !== '') {
                        return helperHtml;
                    }
                    var value = getDisplayValue(row, name);
                    return escapeHtml(safeString(value));
                }
            });
        });

        columns.push({
            data: null,
            orderable: false,
            searchable: false,
            render: function (data, type, row) {
                return buildActionButtons(row, cfg);
            }
        });

        window.jQuery('#generatedTable').DataTable({
            processing: true,
            serverSide: true,
            searching: true,
            ordering: true,
            lengthChange: true,
            pageLength: 10,
            scrollX: true,
            language: {
                url: cfg.languageUrl || ''
            },
            ajax: {
                url: cfg.datatableUrl,
                type: 'GET'
            },
            columns: columns
        });
    }

    function bindGenericModalHandlers() {
        document.querySelectorAll('[data-cm-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-cm-open') || '';
                if (id !== '') openModal(id);
            });
        });

        document.querySelectorAll('[data-cm-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                closeModal(btn);
            });
        });

        document.querySelectorAll('[data-cm-bg]').forEach(function (bg) {
            bg.addEventListener('click', function (event) {
                if (event.target === bg) {
                    closeModal(bg);
                }
            });
        });
    }

    function bindEditButtons() {
        var editForm = document.getElementById('formEditGenerated');
        document.addEventListener('click', function (event) {
            var button = event.target instanceof Element ? event.target.closest('.btn-generated-edit') : null;
            if (!(button instanceof HTMLElement)) return;
            if (!editForm) return;

            var action = button.getAttribute('data-action') || '';
            if (action !== '') {
                editForm.setAttribute('action', action);
            }

            var id = Number(button.getAttribute('data-id') || '0');
            var row = Number.isFinite(id) && id > 0 && rowCache[id] ? rowCache[id] : {};
            Object.keys(row).forEach(function (key) {
                var el = document.getElementById('edit_' + key);
                if (!el) return;
                var val = row[key] == null ? '' : String(row[key]);
                if (el.tagName === 'TEXTAREA' || el.tagName === 'INPUT' || el.tagName === 'SELECT') {
                    el.value = val;
                }
            });

            openModal('cmEditGenerated');
        });
    }

    function bindDeleteButtons() {
        var deleteForm = document.getElementById('formDeleteGenerated');
        var deleteLabel = document.getElementById('delete_generated_label');
        document.addEventListener('click', function (event) {
            var button = event.target instanceof Element ? event.target.closest('.btn-generated-delete') : null;
            if (!(button instanceof HTMLElement)) return;
            if (!deleteForm) return;

            var action = button.getAttribute('data-action') || '';
            var label = button.getAttribute('data-label') || '-';
            if (action !== '') {
                deleteForm.setAttribute('action', action);
            }
            if (deleteLabel) {
                deleteLabel.textContent = label;
            }
            openModal('cmDeleteGenerated');
        });
    }

    function bindDetailButtons() {
        var titleEl = document.getElementById('cmDetailGeneratedTitle');
        var contentEl = document.getElementById('generated_detail_content');
        var cfg = window.generatedCrudConfig || {};
        document.addEventListener('click', function (event) {
            var button = event.target instanceof Element ? event.target.closest('.btn-generated-detail') : null;
            if (!(button instanceof HTMLElement)) return;

            var id = Number(button.getAttribute('data-id') || '0');
            if (!Number.isFinite(id) || id <= 0) return;
            var row = rowCache[id] || null;
            if (!row || typeof row !== 'object') return;

            if (titleEl) {
                var nama = getRowDisplayName(row);
                titleEl.textContent = 'Detail Data - ' + nama;
            }

            if (contentEl) {
                var preview = resolveImagePreview(row, cfg);
                var html = '<div class="generated-detail-layout">';
                html += '<div class="panel generated-detail-media">';
                html += '<div class="panel-head"><span class="panel-title">Gambar</span></div>';
                html += '<div class="panel-body">';
                html += createImageFallbackHtml(preview.url, preview.initials, preview.alt, 'generated-detail-preview');
                html += '</div></div>';
                html += '<div class="panel generated-detail-info">';
                html += '<div class="panel-head"><span class="panel-title">Detail</span></div>';
                html += '<div class="panel-body"><div class="table-responsive"><table class="dtable table-sm align-middle mb-0 generated-detail-table"><tbody>';
                var displayColumns = Array.isArray(cfg.displayColumns) ? cfg.displayColumns : [];
                displayColumns.forEach(function (column) {
                    var name = String((column && column.name) || '').trim();
                    if (name === '') return;
                    if (looksLikeImageFieldName(name)) return;
                    var label = String((column && column.label) || name);
                    var helperHtml = getDisplayHtml(row, name);
                    var displayValue = helperHtml !== '' ? helperHtml : escapeHtml(safeString(getDisplayValue(row, name)));
                    html += '<tr>';
                    html += '<th>' + escapeHtml(label) + '</th>';
                    html += '<td>' + displayValue + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div></div>';
                html += '</div>';
                html += '</div>';
                contentEl.innerHTML = html;
            }

            openModal('cmDetailGenerated');
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });

    function init() {
        initDatatable();
        bindGenericModalHandlers();
        bindEditButtons();
        bindDeleteButtons();
        bindDetailButtons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

JS;
    }

    private function buildRouteSnippetContent(string $controllerName, string $routePrefix): string
    {
        return <<<PHP
<?php

use App\Controllers\\{$controllerName};
use App\Middleware\Authenticate;

\$router->get('/{$routePrefix}', [{$controllerName}::class, 'index'])->withMiddleware(Authenticate::class);
\$router->get('/{$routePrefix}/datatable', [{$controllerName}::class, 'datatable'])->withMiddleware(Authenticate::class);
\$router->post('/{$routePrefix}', [{$controllerName}::class, 'store'])->withMiddleware(Authenticate::class);
\$router->post('/{$routePrefix}/{id}/update', [{$controllerName}::class, 'update'])->withMiddleware(Authenticate::class);
\$router->post('/{$routePrefix}/{id}/delete', [{$controllerName}::class, 'destroy'])->withMiddleware(Authenticate::class);

PHP;
    }
}
