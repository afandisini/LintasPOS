<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

class MenuGeneratorService
{
    private MenuGeneratorFieldDetectorService $fieldDetector;
    private MenuGeneratorFileService $fileService;
    private MenuGeneratorCleanupService $cleanupService;

    public function __construct(
        ?MenuGeneratorFieldDetectorService $fieldDetector = null,
        ?MenuGeneratorFileService $fileService = null,
        ?MenuGeneratorCleanupService $cleanupService = null
    ) {
        $this->fieldDetector = $fieldDetector ?? new MenuGeneratorFieldDetectorService();
        $this->fileService = $fileService ?? new MenuGeneratorFileService();
        $this->cleanupService = $cleanupService ?? new MenuGeneratorCleanupService();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function datatable(array $params): array
    {
        $pdo = Database::connection();
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
        $columns = ['id', 'module_name', 'table_name', 'status', 'updated_at'];
        $orderIndex = (int) ($params['order'][0]['column'] ?? 0);
        $orderColumn = $columns[$orderIndex] ?? 'id';
        $orderDir = strtolower((string) ($params['order'][0]['dir'] ?? 'desc'));
        $orderDir = $orderDir === 'asc' ? 'asc' : 'desc';

        $where = ' WHERE deleted_at IS NULL';
        $bind = [];
        if ($search !== '') {
            $where .= ' AND (module_name LIKE :search_module_name OR table_name LIKE :search_table_name OR module_slug LIKE :search_module_slug OR status LIKE :search_status)';
            $like = '%' . $search . '%';
            $bind['search_module_name'] = $like;
            $bind['search_table_name'] = $like;
            $bind['search_module_slug'] = $like;
            $bind['search_status'] = $like;
        }

        $countStmt = $pdo->query('SELECT COUNT(*) FROM menu_generator WHERE deleted_at IS NULL');
        $recordsTotal = (int) $countStmt->fetchColumn();

        $filteredStmt = $pdo->prepare('SELECT COUNT(*) FROM menu_generator' . $where);
        $filteredStmt->execute($bind);
        $recordsFiltered = (int) $filteredStmt->fetchColumn();

        $sql = 'SELECT id, module_name, module_slug, table_name, controller_name, route_prefix, status, last_generated_at, updated_at '
            . 'FROM menu_generator'
            . $where
            . ' ORDER BY ' . $orderColumn . ' ' . $orderDir
            . ' LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        foreach ($bind as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $length, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $start, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            $rows = [];
        }

        $data = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $data[] = [
                'id' => $id,
                'module_name' => (string) ($row['module_name'] ?? ''),
                'module_slug' => (string) ($row['module_slug'] ?? ''),
                'table_name' => (string) ($row['table_name'] ?? ''),
                'controller_name' => (string) ($row['controller_name'] ?? ''),
                'route_prefix' => (string) ($row['route_prefix'] ?? ''),
                'status' => (string) ($row['status'] ?? 'draft'),
                'last_generated_at' => (string) ($row['last_generated_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'signed_token' => signed_id_encode($id),
            ];
        }

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    /**
     * @return array{table_name: string, columns: array<int, array<string, mixed>>, fields: array<int, array<string, mixed>>}
     */
    public function scanTable(string $tableName): array
    {
        return $this->fieldDetector->scanTable($tableName);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function store(array $payload, int $actorId): int
    {
        $pdo = Database::connection();
        $data = $this->normalizePayload($payload);
        if ($data['module_slug'] === '' || $data['table_name'] === '') {
            throw new \RuntimeException('Data generator tidak valid.');
        }

        $fields = $this->extractFieldsPayload($payload, $data['table_name']);
        if ($fields === []) {
            throw new \RuntimeException('Field tabel tidak ditemukan. Jalankan scan table.');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO menu_generator (module_name, module_slug, table_name, controller_name, model_name, view_folder, route_prefix, menu_title, menu_icon, parent_menu_key, menu_order, description, layout_name, datatable_enabled, datatable_mode, datatable_ajax_url, datatable_default_order_column, datatable_default_order_dir, datatable_page_length, signed_id_enabled, signed_id_driver, delete_method, use_create, use_edit, use_delete, use_detail, use_bulk_delete, use_soft_delete, use_modal_form, helper_relation_enabled, helper_image_enabled, helper_file_enabled, helper_date_enabled, helper_currency_enabled, status, created_by, updated_by, created_at, updated_at) '
                . 'VALUES (:module_name, :module_slug, :table_name, :controller_name, :model_name, :view_folder, :route_prefix, :menu_title, :menu_icon, :parent_menu_key, :menu_order, :description, :layout_name, :datatable_enabled, :datatable_mode, :datatable_ajax_url, :datatable_default_order_column, :datatable_default_order_dir, :datatable_page_length, :signed_id_enabled, :signed_id_driver, :delete_method, :use_create, :use_edit, :use_delete, :use_detail, :use_bulk_delete, :use_soft_delete, :use_modal_form, :helper_relation_enabled, :helper_image_enabled, :helper_file_enabled, :helper_date_enabled, :helper_currency_enabled, :status, :created_by, :updated_by, NOW(), NOW())'
            );
            $stmt->execute($data + [
                'created_by' => $actorId > 0 ? $actorId : null,
                'updated_by' => $actorId > 0 ? $actorId : null,
            ]);

            $generatorId = (int) $pdo->lastInsertId();
            $this->replaceFields($pdo, $generatorId, $fields);
            $this->insertLog($pdo, $generatorId, 'scan_table', 'Konfigurasi awal disimpan dari hasil scan tabel.', [
                'table_name' => $data['table_name'],
                'field_count' => count($fields),
            ], $actorId);

            $pdo->commit();
            return $generatorId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(int $generatorId, array $payload, int $actorId): void
    {
        if ($generatorId <= 0) {
            throw new \RuntimeException('ID generator tidak valid.');
        }

        $pdo = Database::connection();
        $data = $this->normalizePayload($payload);
        $fields = $this->extractFieldsPayload($payload, $data['table_name']);
        if ($fields === []) {
            throw new \RuntimeException('Field tabel tidak ditemukan. Jalankan scan table.');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE menu_generator SET module_name = :module_name, module_slug = :module_slug, table_name = :table_name, controller_name = :controller_name, model_name = :model_name, view_folder = :view_folder, route_prefix = :route_prefix, menu_title = :menu_title, menu_icon = :menu_icon, parent_menu_key = :parent_menu_key, menu_order = :menu_order, description = :description, layout_name = :layout_name, datatable_enabled = :datatable_enabled, datatable_mode = :datatable_mode, datatable_ajax_url = :datatable_ajax_url, datatable_default_order_column = :datatable_default_order_column, datatable_default_order_dir = :datatable_default_order_dir, datatable_page_length = :datatable_page_length, signed_id_enabled = :signed_id_enabled, signed_id_driver = :signed_id_driver, delete_method = :delete_method, use_create = :use_create, use_edit = :use_edit, use_delete = :use_delete, use_detail = :use_detail, use_bulk_delete = :use_bulk_delete, use_soft_delete = :use_soft_delete, use_modal_form = :use_modal_form, helper_relation_enabled = :helper_relation_enabled, helper_image_enabled = :helper_image_enabled, helper_file_enabled = :helper_file_enabled, helper_date_enabled = :helper_date_enabled, helper_currency_enabled = :helper_currency_enabled, updated_by = :updated_by, updated_at = NOW() '
                . 'WHERE id = :id AND deleted_at IS NULL'
            );
            $stmt->execute($data + [
                'id' => $generatorId,
                'updated_by' => $actorId > 0 ? $actorId : null,
            ]);

            $this->replaceFields($pdo, $generatorId, $fields);
            $this->insertLog($pdo, $generatorId, 'sync_fields', 'Konfigurasi generator diperbarui.', [
                'table_name' => $data['table_name'],
                'field_count' => count($fields),
            ], $actorId);

            $pdo->commit();
            $this->syncGeneratedRoutesInWebFile();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function findByToken(string $token): array
    {
        $id = signed_id_decode($token);
        if ($id === null || $id <= 0) {
            return [];
        }

        return $this->findById($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function findById(int $id): array
    {
        if ($id <= 0) {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM menu_generator WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return [];
        }

        $row['signed_token'] = signed_id_encode((int) ($row['id'] ?? 0));
        return $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFields(int $generatorId): array
    {
        if ($generatorId <= 0) {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM menu_generator_fields WHERE menu_generator_id = :id ORDER BY field_order ASC, id ASC');
        $stmt->execute(['id' => $generatorId]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function generate(int $generatorId, int $actorId): void
    {
        $generator = $this->findById($generatorId);
        if ($generator === []) {
            throw new \RuntimeException('Konfigurasi generator tidak ditemukan.');
        }

        $fields = $this->getFields($generatorId);
        if ($fields === []) {
            throw new \RuntimeException('Field generator belum tersedia.');
        }

        $generatedFiles = $this->fileService->generateCrudFiles($generator, $fields);
        if ($generatedFiles === []) {
            throw new \RuntimeException('Tidak ada file yang di-generate.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $deleteOld = $pdo->prepare('DELETE FROM menu_generator_files WHERE menu_generator_id = :id');
            $deleteOld->execute(['id' => $generatorId]);

            $insert = $pdo->prepare(
                'INSERT INTO menu_generator_files (menu_generator_id, file_type, file_path, file_name, checksum_sha1, is_generated, created_at, updated_at) '
                . 'VALUES (:menu_generator_id, :file_type, :file_path, :file_name, :checksum_sha1, :is_generated, NOW(), NOW())'
            );
            foreach ($generatedFiles as $file) {
                $insert->execute([
                    'menu_generator_id' => $generatorId,
                    'file_type' => (string) ($file['file_type'] ?? 'partial'),
                    'file_path' => (string) ($file['file_path'] ?? ''),
                    'file_name' => (string) ($file['file_name'] ?? ''),
                    'checksum_sha1' => (string) ($file['checksum_sha1'] ?? ''),
                    'is_generated' => 1,
                ]);
            }

            $update = $pdo->prepare(
                'UPDATE menu_generator SET status = :status, last_generated_at = NOW(), last_generated_by = :last_generated_by, updated_by = :updated_by, updated_at = NOW() '
                . 'WHERE id = :id'
            );
            $update->execute([
                'status' => 'generated',
                'last_generated_by' => $actorId > 0 ? $actorId : null,
                'updated_by' => $actorId > 0 ? $actorId : null,
                'id' => $generatorId,
            ]);

            $this->insertLog($pdo, $generatorId, 'generate', 'Generate file CRUD berhasil.', [
                'files' => $generatedFiles,
            ], $actorId);

            $pdo->commit();
            $this->syncGeneratedRoutesInWebFile();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function deleteGenerated(int $generatorId, int $actorId): array
    {
        if ($generatorId <= 0) {
            throw new \RuntimeException('ID generator tidak valid.');
        }

        $pdo = Database::connection();
        $filesStmt = $pdo->prepare('SELECT id, file_path FROM menu_generator_files WHERE menu_generator_id = :id');
        $filesStmt->execute(['id' => $generatorId]);
        $files = $filesStmt->fetchAll();
        if (!is_array($files)) {
            $files = [];
        }

        $result = $this->cleanupService->cleanupGeneratedFiles($files);
        $pdo->beginTransaction();
        try {
            $delete = $pdo->prepare('DELETE FROM menu_generator_files WHERE menu_generator_id = :id');
            $delete->execute(['id' => $generatorId]);

            $update = $pdo->prepare(
                'UPDATE menu_generator SET status = :status, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL'
            );
            $update->execute([
                'status' => 'draft',
                'updated_by' => $actorId > 0 ? $actorId : null,
                'id' => $generatorId,
            ]);

            $this->insertLog($pdo, $generatorId, 'delete_generated', 'File hasil generate dihapus.', $result, $actorId);
            $pdo->commit();
            $this->syncGeneratedRoutesInWebFile();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $result;
    }

    public function deleteConfig(int $generatorId, int $actorId): void
    {
        if ($generatorId <= 0) {
            throw new \RuntimeException('ID generator tidak valid.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE menu_generator SET status = :status, deleted_at = NOW(), updated_by = :updated_by, updated_at = NOW() '
            . 'WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'status' => 'disabled',
            'updated_by' => $actorId > 0 ? $actorId : null,
            'id' => $generatorId,
        ]);

        $this->insertLog($pdo, $generatorId, 'disable', 'Konfigurasi generator dinonaktifkan (soft delete).', [], $actorId);
        $this->syncGeneratedRoutesInWebFile();
    }

    /**
     * @return array<int, string>
     */
    public function listTables(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SHOW TABLES');
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
        if (!is_array($rows)) {
            return [];
        }

        $tables = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row[0] ?? ''));
            if ($name === '' || str_starts_with($name, 'menu_generator')) {
                continue;
            }
            $tables[] = $name;
        }
        sort($tables);
        return $tables;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $moduleName = trim((string) ($payload['module_name'] ?? ''));
        $moduleSlug = $this->slug((string) ($payload['module_slug'] ?? ($moduleName !== '' ? $moduleName : '')));
        $tableName = $this->slug((string) ($payload['table_name'] ?? ''));
        $controllerName = trim((string) ($payload['controller_name'] ?? ''));
        $viewFolder = $this->slug((string) ($payload['view_folder'] ?? $moduleSlug));
        $routePrefix = $this->slug((string) ($payload['route_prefix'] ?? $moduleSlug));
        $menuTitle = trim((string) ($payload['menu_title'] ?? $moduleName));
        $menuIcon = trim((string) ($payload['menu_icon'] ?? 'bi bi-grid-3x3-gap-fill'));
        $parentMenuKey = trim((string) ($payload['parent_menu_key'] ?? ''));

        if ($moduleName === '') {
            $moduleName = ucwords(str_replace('_', ' ', $moduleSlug));
        }
        if ($controllerName === '') {
            $controllerName = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $moduleSlug))) . 'Controller';
        }

        $orderDir = strtolower((string) ($payload['datatable_default_order_dir'] ?? 'desc'));
        $orderDir = $orderDir === 'asc' ? 'asc' : 'desc';
        $status = trim((string) ($payload['status'] ?? 'draft'));
        if (!in_array($status, ['draft', 'generated', 'disabled'], true)) {
            $status = 'draft';
        }

        return [
            'module_name' => $moduleName,
            'module_slug' => $moduleSlug,
            'table_name' => $tableName,
            'controller_name' => $controllerName,
            'model_name' => null,
            'view_folder' => $viewFolder,
            'route_prefix' => $routePrefix,
            'menu_title' => $menuTitle !== '' ? $menuTitle : $moduleName,
            'menu_icon' => $menuIcon,
            'parent_menu_key' => $parentMenuKey !== '' ? substr($parentMenuKey, 0, 150) : null,
            'menu_order' => (int) ($payload['menu_order'] ?? 0),
            'description' => trim((string) ($payload['description'] ?? '')),
            'layout_name' => 'admin',
            'datatable_enabled' => 1,
            'datatable_mode' => 'server_side',
            'datatable_ajax_url' => '/' . trim($routePrefix, '/') . '/datatable',
            'datatable_default_order_column' => 'id',
            'datatable_default_order_dir' => $orderDir,
            'datatable_page_length' => 10,
            'signed_id_enabled' => 1,
            'signed_id_driver' => 'hmac',
            'delete_method' => 'POST',
            'use_create' => 1,
            'use_edit' => 1,
            'use_delete' => 1,
            'use_detail' => 0,
            'use_bulk_delete' => 0,
            'use_soft_delete' => 1,
            'use_modal_form' => 1,
            'helper_relation_enabled' => 1,
            'helper_image_enabled' => 1,
            'helper_file_enabled' => 1,
            'helper_date_enabled' => 1,
            'helper_currency_enabled' => 1,
            'status' => $status,
        ];
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\\/-]+/', '_', $value);
        $value = is_string($value) ? trim($value, '_/-') : '';
        return substr($value, 0, 150);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractFieldsPayload(array $payload, string $tableName): array
    {
        $fieldsRaw = $payload['fields_json'] ?? null;
        if (is_string($fieldsRaw) && trim($fieldsRaw) !== '') {
            $decoded = json_decode($fieldsRaw, true);
            if (is_array($decoded)) {
                $items = [];
                foreach ($decoded as $item) {
                    if (is_array($item)) {
                        $items[] = $item;
                    }
                }
                if ($items !== []) {
                    return $items;
                }
            }
        }

        if ($tableName === '') {
            return [];
        }

        $scan = $this->fieldDetector->scanTable($tableName);
        return $scan['fields'] ?? [];
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     */
    private function replaceFields(\PDO $pdo, int $generatorId, array $fields): void
    {
        $delete = $pdo->prepare('DELETE FROM menu_generator_fields WHERE menu_generator_id = :id');
        $delete->execute(['id' => $generatorId]);

        $insert = $pdo->prepare(
            'INSERT INTO menu_generator_fields (menu_generator_id, field_name, field_label, db_type, db_length, db_default, is_nullable, is_primary, is_unique, html_type, field_order, placeholder_text, help_text, show_in_index, show_in_form, show_in_create, show_in_edit, show_in_detail, datatable_visible, datatable_searchable, datatable_sortable, datatable_class, datatable_render, is_relation, relation_type, relation_table, relation_key, relation_label_field, relation_where_json, relation_order_by, relation_helper, upload_type, upload_dir, allowed_extensions, allowed_mime_types, max_file_size_kb, helper_format, auto_rule, is_system_field, created_at, updated_at) '
            . 'VALUES (:menu_generator_id, :field_name, :field_label, :db_type, :db_length, :db_default, :is_nullable, :is_primary, :is_unique, :html_type, :field_order, :placeholder_text, :help_text, :show_in_index, :show_in_form, :show_in_create, :show_in_edit, :show_in_detail, :datatable_visible, :datatable_searchable, :datatable_sortable, :datatable_class, :datatable_render, :is_relation, :relation_type, :relation_table, :relation_key, :relation_label_field, :relation_where_json, :relation_order_by, :relation_helper, :upload_type, :upload_dir, :allowed_extensions, :allowed_mime_types, :max_file_size_kb, :helper_format, :auto_rule, :is_system_field, NOW(), NOW())'
        );

        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }
            $insert->execute([
                'menu_generator_id' => $generatorId,
                'field_name' => (string) ($field['field_name'] ?? ''),
                'field_label' => (string) ($field['field_label'] ?? ''),
                'db_type' => (string) ($field['db_type'] ?? 'varchar'),
                'db_length' => $field['db_length'] ?? null,
                'db_default' => $field['db_default'] ?? null,
                'is_nullable' => (int) ($field['is_nullable'] ?? 1),
                'is_primary' => (int) ($field['is_primary'] ?? 0),
                'is_unique' => (int) ($field['is_unique'] ?? 0),
                'html_type' => (string) ($field['html_type'] ?? 'text'),
                'field_order' => (int) ($field['field_order'] ?? ((int) $index + 1)),
                'placeholder_text' => $field['placeholder_text'] ?? null,
                'help_text' => $field['help_text'] ?? null,
                'show_in_index' => (int) ($field['show_in_index'] ?? 1),
                'show_in_form' => (int) ($field['show_in_form'] ?? 1),
                'show_in_create' => (int) ($field['show_in_create'] ?? 1),
                'show_in_edit' => (int) ($field['show_in_edit'] ?? 1),
                'show_in_detail' => (int) ($field['show_in_detail'] ?? 1),
                'datatable_visible' => (int) ($field['datatable_visible'] ?? 1),
                'datatable_searchable' => (int) ($field['datatable_searchable'] ?? 1),
                'datatable_sortable' => (int) ($field['datatable_sortable'] ?? 1),
                'datatable_class' => $field['datatable_class'] ?? null,
                'datatable_render' => $field['datatable_render'] ?? null,
                'is_relation' => (int) ($field['is_relation'] ?? 0),
                'relation_type' => $field['relation_type'] ?? null,
                'relation_table' => $field['relation_table'] ?? null,
                'relation_key' => $field['relation_key'] ?? null,
                'relation_label_field' => $field['relation_label_field'] ?? null,
                'relation_where_json' => isset($field['relation_where_json']) ? (is_string($field['relation_where_json']) ? $field['relation_where_json'] : json_encode($field['relation_where_json'], JSON_UNESCAPED_UNICODE)) : null,
                'relation_order_by' => $field['relation_order_by'] ?? null,
                'relation_helper' => $field['relation_helper'] ?? null,
                'upload_type' => $field['upload_type'] ?? null,
                'upload_dir' => $field['upload_dir'] ?? null,
                'allowed_extensions' => $field['allowed_extensions'] ?? null,
                'allowed_mime_types' => $field['allowed_mime_types'] ?? null,
                'max_file_size_kb' => $field['max_file_size_kb'] ?? null,
                'helper_format' => (string) ($field['helper_format'] ?? 'none'),
                'auto_rule' => $field['auto_rule'] ?? null,
                'is_system_field' => (int) ($field['is_system_field'] ?? 0),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function insertLog(
        \PDO $pdo,
        int $generatorId,
        string $actionType,
        string $actionNote,
        array $snapshot,
        int $actorId
    ): void {
        $allowedActions = ['scan_table', 'generate', 'regenerate', 'delete_generated', 'disable', 'enable', 'sync_fields'];
        if (!in_array($actionType, $allowedActions, true)) {
            $actionType = 'sync_fields';
        }

        $stmt = $pdo->prepare(
            'INSERT INTO menu_generator_logs (menu_generator_id, action_type, action_note, snapshot_json, created_by, created_at) '
            . 'VALUES (:menu_generator_id, :action_type, :action_note, :snapshot_json, :created_by, NOW())'
        );
        $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        $stmt->execute([
            'menu_generator_id' => $generatorId,
            'action_type' => $actionType,
            'action_note' => $actionNote,
            'snapshot_json' => is_string($snapshotJson) ? $snapshotJson : null,
            'created_by' => $actorId > 0 ? $actorId : null,
        ]);
    }

    /**
     * Sinkronkan route generated ke routes/web.php agar route aktif tanpa import snippet manual.
     */
    private function syncGeneratedRoutesInWebFile(): void
    {
        $webRoutesPath = app()->basePath('routes/web.php');
        if (!is_file($webRoutesPath)) {
            return;
        }

        $content = file_get_contents($webRoutesPath);
        if (!is_string($content) || $content === '') {
            return;
        }

        $definitions = $this->fetchGeneratedRouteDefinitions();
        $blockLines = [
            '// [MenuGenerator:Start]',
            '// Auto-generated routes from menu_generator (status=generated).',
        ];

        foreach ($definitions as $definition) {
            $routePrefix = $definition['route_prefix'];
            $controllerName = $definition['controller_name'];
            $blockLines[] = '$router->get(\'/' . $routePrefix . '\', [\\App\\Controllers\\' . $controllerName . '::class, \'index\'])->withMiddleware(\\App\\Middleware\\Authenticate::class);';
            $blockLines[] = '$router->get(\'/' . $routePrefix . '/datatable\', [\\App\\Controllers\\' . $controllerName . '::class, \'datatable\'])->withMiddleware(\\App\\Middleware\\Authenticate::class);';
            $blockLines[] = '$router->post(\'/' . $routePrefix . '\', [\\App\\Controllers\\' . $controllerName . '::class, \'store\'])->withMiddleware(\\App\\Middleware\\Authenticate::class);';
            $blockLines[] = '$router->post(\'/' . $routePrefix . '/{id}/update\', [\\App\\Controllers\\' . $controllerName . '::class, \'update\'])->withMiddleware(\\App\\Middleware\\Authenticate::class);';
            $blockLines[] = '$router->post(\'/' . $routePrefix . '/{id}/delete\', [\\App\\Controllers\\' . $controllerName . '::class, \'destroy\'])->withMiddleware(\\App\\Middleware\\Authenticate::class);';
        }

        $blockLines[] = '// [MenuGenerator:End]';
        $block = implode(PHP_EOL, $blockLines);

        $pattern = '/^[ \t]*\/\/ \[MenuGenerator:Start\][\s\S]*?^[ \t]*\/\/ \[MenuGenerator:End\]\R?/m';
        if (preg_match($pattern, $content) === 1) {
            $newContent = (string) preg_replace($pattern, $block . PHP_EOL, $content, 1);
        } else {
            $anchor = '$router->post(\'/logout\'';
            $position = strpos($content, $anchor);
            if ($position !== false) {
                $newContent = substr($content, 0, $position) . $block . PHP_EOL . PHP_EOL . substr($content, $position);
            } else {
                $newContent = rtrim($content) . PHP_EOL . PHP_EOL . $block . PHP_EOL;
            }
        }

        if ($newContent !== $content) {
            file_put_contents($webRoutesPath, $newContent);
        }
    }

    /**
     * @return array<int, array{route_prefix: string, controller_name: string}>
     */
    private function fetchGeneratedRouteDefinitions(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT route_prefix, controller_name '
            . 'FROM menu_generator '
            . 'WHERE deleted_at IS NULL AND status = \'generated\' '
            . 'ORDER BY menu_order ASC, id ASC'
        );
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $routePrefix = trim((string) ($row['route_prefix'] ?? ''), '/');
            $controllerName = trim((string) ($row['controller_name'] ?? ''));
            if ($routePrefix === '' || $controllerName === '') {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9_\\/-]+$/', $routePrefix)) {
                continue;
            }
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $controllerName)) {
                continue;
            }

            $result[] = [
                'route_prefix' => $routePrefix,
                'controller_name' => $controllerName,
            ];
        }

        return $result;
    }
}
