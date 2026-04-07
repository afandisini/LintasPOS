<?php

declare(strict_types=1);

namespace App\Services;

class MenuGeneratorFieldDetectorService
{
    /**
     * @return array{table_name: string, columns: array<int, array<string, mixed>>, fields: array<int, array<string, mixed>>}
     */
    public function scanTable(string $tableName): array
    {
        $tableName = $this->sanitizeIdentifier($tableName);
        if ($tableName === '') {
            return [
                'table_name' => '',
                'columns' => [],
                'fields' => [],
            ];
        }

        $pdo = Database::connection();
        $databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($databaseName === '') {
            return [
                'table_name' => $tableName,
                'columns' => [],
                'fields' => [],
            ];
        }

        $columnsStmt = $pdo->prepare(
            'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA, ORDINAL_POSITION '
            . 'FROM INFORMATION_SCHEMA.COLUMNS '
            . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table '
            . 'ORDER BY ORDINAL_POSITION ASC'
        );
        $columnsStmt->execute([
            'schema' => $databaseName,
            'table' => $tableName,
        ]);
        $columns = $columnsStmt->fetchAll();
        if (!is_array($columns)) {
            $columns = [];
        }

        $uniqueColumns = $this->fetchUniqueColumns($pdo, $databaseName, $tableName);
        $fields = [];
        foreach ($columns as $index => $column) {
            if (!is_array($column)) {
                continue;
            }
            $fields[] = $this->detectField($tableName, $column, $uniqueColumns, ((int) $index + 1));
        }

        return [
            'table_name' => $tableName,
            'columns' => $columns,
            'fields' => $fields,
        ];
    }

    private function sanitizeIdentifier(string $name): string
    {
        $name = trim(strtolower($name));
        if ($name === '' || !preg_match('/^[a-z0-9_]+$/', $name)) {
            return '';
        }

        return substr($name, 0, 150);
    }

    /**
     * @return array<string, bool>
     */
    private function fetchUniqueColumns(\PDO $pdo, string $schemaName, string $tableName): array
    {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT COLUMN_NAME '
            . 'FROM INFORMATION_SCHEMA.STATISTICS '
            . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table '
            . 'AND NON_UNIQUE = 0 AND INDEX_NAME <> \'PRIMARY\''
        );
        $stmt->execute([
            'schema' => $schemaName,
            'table' => $tableName,
        ]);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $columnName = strtolower(trim((string) ($row['COLUMN_NAME'] ?? '')));
            if ($columnName !== '') {
                $result[$columnName] = true;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $column
     * @param array<string, bool> $uniqueColumns
     * @return array<string, mixed>
     */
    private function detectField(string $tableName, array $column, array $uniqueColumns, int $order): array
    {
        $fieldName = strtolower((string) ($column['COLUMN_NAME'] ?? ''));
        $dbType = strtolower((string) ($column['DATA_TYPE'] ?? 'varchar'));
        $dbLength = (string) ($column['COLUMN_TYPE'] ?? '');
        $isNullable = strtoupper((string) ($column['IS_NULLABLE'] ?? 'YES')) === 'YES';
        $columnKey = strtoupper((string) ($column['COLUMN_KEY'] ?? ''));
        $isPrimary = $columnKey === 'PRI';
        $isUnique = (bool) ($uniqueColumns[$fieldName] ?? false);
        $default = $column['COLUMN_DEFAULT'] ?? null;

        $htmlType = $this->guessHtmlType($fieldName, $dbType);
        $isSystemField = in_array($fieldName, ['id', 'created_at', 'updated_at', 'deleted_at'], true);
        if ($isSystemField) {
            $htmlType = 'hidden';
        }

        $relation = $this->guessRelation($fieldName);
        $upload = $this->guessUpload($fieldName);
        $helperFormat = $this->guessHelperFormat($fieldName, $dbType);

        return [
            'field_name' => $fieldName,
            'field_label' => $this->fieldLabel($fieldName),
            'db_type' => $dbType,
            'db_length' => $dbLength !== '' ? $dbLength : null,
            'db_default' => $default !== '' ? $default : null,
            'is_nullable' => $isNullable ? 1 : 0,
            'is_primary' => $isPrimary ? 1 : 0,
            'is_unique' => $isUnique ? 1 : 0,
            'html_type' => $htmlType,
            'field_order' => $order,
            'placeholder_text' => $isSystemField ? null : ('Masukkan ' . strtolower($this->fieldLabel($fieldName))),
            'help_text' => null,
            'show_in_index' => $isSystemField ? 0 : 1,
            'show_in_form' => $isSystemField ? 0 : 1,
            'show_in_create' => $isSystemField ? 0 : 1,
            'show_in_edit' => $isSystemField ? 0 : 1,
            'show_in_detail' => 1,
            'datatable_visible' => $isSystemField ? 0 : 1,
            'datatable_searchable' => $isSystemField ? 0 : 1,
            'datatable_sortable' => $fieldName === 'deleted_at' ? 0 : 1,
            'datatable_class' => null,
            'datatable_render' => $helperFormat,
            'is_relation' => $relation['is_relation'],
            'relation_type' => $relation['relation_type'],
            'relation_table' => $relation['relation_table'],
            'relation_key' => $relation['relation_key'],
            'relation_label_field' => $relation['relation_label_field'],
            'relation_where_json' => null,
            'relation_order_by' => null,
            'relation_helper' => $relation['relation_helper'],
            'upload_type' => $upload['upload_type'],
            'upload_dir' => $upload['upload_dir'] !== null ? str_replace('{table}', $tableName, $upload['upload_dir']) : null,
            'allowed_extensions' => $upload['allowed_extensions'],
            'allowed_mime_types' => $upload['allowed_mime_types'],
            'max_file_size_kb' => $upload['max_file_size_kb'],
            'helper_format' => $helperFormat,
            'auto_rule' => $this->autoRule($fieldName),
            'is_system_field' => $isSystemField ? 1 : 0,
        ];
    }

    private function fieldLabel(string $fieldName): string
    {
        $name = preg_replace('/(_id|_img|_file|_textarea)$/', '', $fieldName);
        $name = str_replace('_', ' ', (string) $name);
        $name = trim($name);
        if ($name === '') {
            return 'Field';
        }

        return ucwords($name);
    }

    private function guessHtmlType(string $fieldName, string $dbType): string
    {
        if (str_ends_with($fieldName, '_textarea')) {
            return 'textarea';
        }
        if (str_ends_with($fieldName, '_img')) {
            return 'image';
        }
        if (str_ends_with($fieldName, '_file')) {
            return 'file';
        }
        if (str_ends_with($fieldName, '_img_id') || str_ends_with($fieldName, '_file_id') || str_ends_with($fieldName, '_id')) {
            return 'select';
        }
        if (in_array($dbType, ['text', 'longtext', 'mediumtext'], true)) {
            return 'textarea';
        }
        if (in_array($dbType, ['date'], true)) {
            return 'date';
        }
        if (in_array($dbType, ['datetime', 'timestamp'], true)) {
            return 'datetime-local';
        }
        if (in_array($dbType, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double'], true)) {
            return 'number';
        }

        return 'text';
    }

    /**
     * @return array{is_relation: int, relation_type: string|null, relation_table: string|null, relation_key: string|null, relation_label_field: string|null, relation_helper: string|null}
     */
    private function guessRelation(string $fieldName): array
    {
        if (str_ends_with($fieldName, '_img_id')) {
            return [
                'is_relation' => 1,
                'relation_type' => 'image_ref',
                'relation_table' => 'filemanager',
                'relation_key' => 'id',
                'relation_label_field' => 'name',
                'relation_helper' => 'image',
            ];
        }

        if (str_ends_with($fieldName, '_file_id')) {
            return [
                'is_relation' => 1,
                'relation_type' => 'file_ref',
                'relation_table' => 'filemanager',
                'relation_key' => 'id',
                'relation_label_field' => 'name',
                'relation_helper' => 'file',
            ];
        }

        if (str_ends_with($fieldName, '_id') && $fieldName !== 'id') {
            $base = substr($fieldName, 0, -3);
            $relationTable = $base !== '' ? $base : null;
            return [
                'is_relation' => 1,
                'relation_type' => 'belongs_to',
                'relation_table' => $relationTable,
                'relation_key' => 'id',
                'relation_label_field' => 'name',
                'relation_helper' => 'relation',
            ];
        }

        return [
            'is_relation' => 0,
            'relation_type' => null,
            'relation_table' => null,
            'relation_key' => null,
            'relation_label_field' => null,
            'relation_helper' => null,
        ];
    }

    /**
     * @return array{upload_type: string|null, upload_dir: string|null, allowed_extensions: string|null, allowed_mime_types: string|null, max_file_size_kb: int|null}
     */
    private function guessUpload(string $fieldName): array
    {
        if (str_ends_with($fieldName, '_img')) {
            return [
                'upload_type' => 'image',
                'upload_dir' => 'filemanager/{table}/{id}',
                'allowed_extensions' => 'jpg,jpeg,png,webp,gif',
                'allowed_mime_types' => 'image/jpeg,image/png,image/webp,image/gif',
                'max_file_size_kb' => 2048,
            ];
        }

        if (str_ends_with($fieldName, '_file')) {
            return [
                'upload_type' => 'file',
                'upload_dir' => 'filemanager/{table}/{id}',
                'allowed_extensions' => 'pdf,doc,docx,xls,xlsx,zip,rar,txt,csv',
                'allowed_mime_types' => 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/csv,application/zip,application/x-rar-compressed',
                'max_file_size_kb' => 5120,
            ];
        }

        return [
            'upload_type' => null,
            'upload_dir' => null,
            'allowed_extensions' => null,
            'allowed_mime_types' => null,
            'max_file_size_kb' => null,
        ];
    }

    private function guessHelperFormat(string $fieldName, string $dbType): string
    {
        if (str_ends_with($fieldName, '_img') || str_ends_with($fieldName, '_img_id')) {
            return 'image';
        }
        if (str_ends_with($fieldName, '_file') || str_ends_with($fieldName, '_file_id')) {
            return 'file';
        }
        if (str_ends_with($fieldName, '_id') && $fieldName !== 'id') {
            return 'relation';
        }
        if (in_array($dbType, ['date'], true)) {
            return 'date_id';
        }
        if (in_array($dbType, ['datetime', 'timestamp'], true)) {
            return 'datetime_id';
        }
        if (in_array($dbType, ['decimal', 'float', 'double'], true)) {
            return 'currency_id';
        }

        return 'none';
    }

    private function autoRule(string $fieldName): string
    {
        if (in_array($fieldName, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
            return 'system:' . $fieldName;
        }
        if (str_ends_with($fieldName, '_img_id')) {
            return 'suffix:img_id';
        }
        if (str_ends_with($fieldName, '_file_id')) {
            return 'suffix:file_id';
        }
        if (str_ends_with($fieldName, '_textarea')) {
            return 'suffix:textarea';
        }
        if (str_ends_with($fieldName, '_img')) {
            return 'suffix:img';
        }
        if (str_ends_with($fieldName, '_file')) {
            return 'suffix:file';
        }
        if (str_ends_with($fieldName, '_id')) {
            return 'suffix:id';
        }

        return 'default:text';
    }
}
