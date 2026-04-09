<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use System\Http\Request;
use System\Http\Response;
use Throwable;

class FileManagerController
{
    private const MAX_FILE_SIZE = 20_971_520; // 20 MB

    public function index(Request $request): Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $authId = (int) ($auth['id'] ?? 0);
        $isAdmin = $this->isAdministrator($auth);
        $module = $this->normalizeModule((string) $request->input('module', ''));
        $refId = $this->normalizeRefId((string) $request->input('ref', ''));
        $page = (int) $request->input('page', '1');
        if ($page < 1) {
            $page = 1;
        }
        $perPage = 10;
        $files = [];
        $modules = [];
        $totalFiles = 0;
        $totalPages = 1;

        try {
            $pdo = Database::connection();
            $params = [];
            $whereSql = ' FROM filemanager WHERE deleted_at IS NULL';

            if ($module !== '') {
                $whereSql .= ' AND module = :module';
                $params['module'] = $module;
            }
            if ($refId !== '') {
                $whereSql .= ' AND ref_id = :ref_id';
                $params['ref_id'] = $refId;
            }
            if (!$isAdmin) {
                $whereSql .= ' AND uploaded_by = :uploaded_by';
                $params['uploaded_by'] = $authId;
            }

            $countStmt = $pdo->prepare('SELECT COUNT(*)' . $whereSql);
            $countStmt->execute($params);
            $totalFiles = (int) $countStmt->fetchColumn();
            $totalPages = max(1, (int) ceil($totalFiles / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }

            $offset = ($page - 1) * $perPage;
            $sql = 'SELECT id, name, module, ref_id, path, mime_type, extension, size_bytes, visibility, uploaded_by, created_at '
                . $whereSql
                . ' ORDER BY id DESC LIMIT :limit OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $files = $stmt->fetchAll();
            if (!is_array($files)) {
                $files = [];
            }
            $modules = $this->collectModules($pdo, $authId, $isAdmin);
        } catch (Throwable) {
            toast_add('Gagal memuat data file manager. Pastikan migrasi filemanager sudah dijalankan.', 'error');
        }

        $html = app()->view()->render('filemanager/index', [
            'title' => 'File Manager',
            'auth' => $auth,
            'activeMenu' => 'filemanager',
            'module' => $module,
            'ref' => $refId,
            'page' => $page,
            'perPage' => $perPage,
            'totalFiles' => $totalFiles,
            'totalPages' => $totalPages,
            'files' => $files,
            'modules' => $modules,
        ]);

        return Response::html($html);
    }

    public function upload(Request $request): Response
    {
        $authId = (int) ($_SESSION['auth']['id'] ?? 0);
        $module = $this->normalizeModule((string) $request->input('module', ''));
        $refId = $this->normalizeRefId((string) $request->input('ref_id', ''));
        $visibility = strtolower(trim((string) $request->input('visibility', 'private')));
        if (!in_array($visibility, ['private', 'public'], true)) {
            $visibility = 'private';
        }

        if ($module === '' || $refId === '') {
            toast_add('Modul dan ID referensi wajib diisi.', 'error');
            return Response::redirect('/filemanager');
        }

        $uploadedFiles = $this->collectUploadedFiles($_FILES, 'upload_files');
        if ($uploadedFiles === []) {
            $uploadedFiles = $this->collectUploadedFiles($_FILES, 'upload_file');
        }
        if ($uploadedFiles === []) {
            toast_add('Tidak ada file yang diupload.', 'error');
            return Response::redirect('/filemanager?module=' . urlencode($module) . '&ref=' . urlencode($refId));
        }

        $baseStorage = app()->basePath('storage/filemanager');
        $targetDir = $baseStorage . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . $refId;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            toast_add('Gagal membuat direktori file manager.', 'error');
            return Response::redirect('/filemanager?module=' . urlencode($module) . '&ref=' . urlencode($refId));
        }

        try {
            $pdo = Database::connection();
            $insertStmt = $pdo->prepare(
                'INSERT INTO filemanager (`name`, module, ref_id, path, filename, mime_type, extension, size_bytes, visibility, uploaded_by, checksum_sha1, create_time, created_at, updated_at) '
                . 'VALUES (:name, :module, :ref_id, :path, :filename, :mime_type, :extension, :size_bytes, :visibility, :uploaded_by, :checksum_sha1, NOW(), NOW(), NOW())'
            );
        } catch (Throwable) {
            toast_add('Gagal menyiapkan upload file.', 'error');
            return Response::redirect('/filemanager?module=' . urlencode($module) . '&ref=' . urlencode($refId));
        }

        $blockedExtensions = ['php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'com', 'py', 'rb', 'pl', 'cgi', 'asp', 'aspx', 'jsp'];

        // MIME whitelist: hanya izinkan tipe file yang aman
        $allowedMimeTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/bmp',
            'application/pdf',
            'text/plain', 'text/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip', 'application/x-zip-compressed',
        ];
        $successCount = 0;
        $failedNames = [];

        foreach ($uploadedFiles as $uploaded) {
            $errorCode = (int) ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE);
            $tmp = (string) ($uploaded['tmp_name'] ?? '');
            $originalName = trim((string) ($uploaded['name'] ?? ''));
            $size = (int) ($uploaded['size'] ?? 0);
            $failedLabel = $originalName !== '' ? $originalName : 'file';

            if ($errorCode !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp) || $size <= 0) {
                $failedNames[] = $failedLabel;
                continue;
            }

            if ($size > self::MAX_FILE_SIZE) {
                $failedNames[] = $failedLabel;
                continue;
            }

            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (in_array($extension, $blockedExtensions, true)) {
                $failedNames[] = $failedLabel;
                continue;
            }

            $safeOriginal = $this->sanitizeFileName($originalName);

            try {
                $storedName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . ($extension !== '' ? '.' . $extension : '');
            } catch (Throwable) {
                $failedNames[] = $failedLabel;
                continue;
            }

            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $storedName;
            if (!move_uploaded_file($tmp, $targetPath)) {
                $failedNames[] = $failedLabel;
                continue;
            }

            // Validasi MIME server-side dengan finfo setelah file dipindah
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detectedMime = (string) $finfo->file($targetPath);
            if (!in_array($detectedMime, $allowedMimeTypes, true)) {
                @unlink($targetPath);
                $failedNames[] = $failedLabel;
                continue;
            }

            $mime = (string) mime_content_type($targetPath);
            $sha1 = (string) sha1_file($targetPath);
            $relativePath = 'filemanager/' . $module . '/' . $refId . '/' . $storedName;

            try {
                $insertStmt->execute([
                    'name' => $safeOriginal,
                    'module' => $module,
                    'ref_id' => $refId,
                    'path' => $relativePath,
                    'filename' => $storedName,
                    'mime_type' => $mime !== '' ? $mime : null,
                    'extension' => $extension !== '' ? $extension : null,
                    'size_bytes' => $size,
                    'visibility' => $visibility,
                    'uploaded_by' => $authId > 0 ? $authId : null,
                    'checksum_sha1' => $sha1 !== '' ? $sha1 : null,
                ]);
                $successCount++;
            } catch (Throwable) {
                @unlink($targetPath);
                $failedNames[] = $failedLabel;
            }
        }

        if ($successCount > 0) {
            toast_add($successCount . ' file berhasil diupload.', 'success');
        }

        if ($failedNames !== []) {
            $preview = implode(', ', array_slice($failedNames, 0, 3));
            if (count($failedNames) > 3) {
                $preview .= ' dan lainnya';
            }
            toast_add(count($failedNames) . ' file gagal diupload: ' . $preview . '.', 'warning');
        }

        if ($successCount <= 0 && $failedNames === []) {
            toast_add('Upload file tidak diproses.', 'warning');
        }

        return Response::redirect('/filemanager?module=' . urlencode($module) . '&ref=' . urlencode($refId));
    }

    public function destroy(Request $request, string $id): Response
    {
        $recordId = (int) $id;
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $authId = (int) ($auth['id'] ?? 0);
        $isAdmin = $this->isAdministrator($auth);
        if ($recordId <= 0) {
            toast_add('Data file tidak valid.', 'error');
            return Response::redirect('/filemanager');
        }

        try {
            $pdo = Database::connection();
            $sql = 'SELECT id, module, ref_id, path FROM filemanager WHERE id = :id AND deleted_at IS NULL';
            if (!$isAdmin) {
                $sql .= ' AND uploaded_by = :uploaded_by';
            }
            $sql .= ' LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $params = ['id' => $recordId];
            if (!$isAdmin) {
                $params['uploaded_by'] = $authId;
            }
            $stmt->execute($params);
            $row = $stmt->fetch();
            if (!is_array($row)) {
                toast_add('File tidak ditemukan atau tidak punya akses.', 'warning');
                return Response::redirect('/filemanager');
            }

            $this->deleteFileRows($pdo, [$row]);

            toast_add('File berhasil dihapus.', 'success');
            $module = $this->normalizeModule((string) $request->input('module', (string) ($row['module'] ?? '')));
            $ref = $this->normalizeRefId((string) $request->input('ref', (string) ($row['ref_id'] ?? '')));
            $page = max(1, (int) $request->input('page', '1'));
            return $this->redirectWithFilters($module, $ref, $page);
        } catch (Throwable) {
            toast_add('Gagal menghapus file.', 'error');
            return Response::redirect('/filemanager');
        }
    }

    public function destroyBulk(Request $request): Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $authId = (int) ($auth['id'] ?? 0);
        $isAdmin = $this->isAdministrator($auth);
        $module = $this->normalizeModule((string) $request->input('module', ''));
        $ref = $this->normalizeRefId((string) $request->input('ref', ''));
        $page = max(1, (int) $request->input('page', '1'));

        $rawIds = $request->input('ids', []);
        if (!is_array($rawIds)) {
            $rawIds = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
        }

        $ids = [];
        foreach ($rawIds as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);

        if ($ids === []) {
            toast_add('Pilih minimal satu file untuk dihapus.', 'warning');
            return $this->redirectWithFilters($module, $ref, $page);
        }

        try {
            $pdo = Database::connection();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = 'SELECT id, module, ref_id, path FROM filemanager WHERE deleted_at IS NULL AND id IN (' . $placeholders . ')';
            if (!$isAdmin) {
                $sql .= ' AND uploaded_by = ?';
                $ids[] = $authId;
            }
            $select = $pdo->prepare($sql);
            $select->execute($ids);
            $rows = $select->fetchAll();
            if (!is_array($rows) || $rows === []) {
                toast_add('Data file tidak ditemukan.', 'warning');
                return $this->redirectWithFilters($module, $ref, $page);
            }

            $this->deleteFileRows($pdo, $rows);
            toast_add(count($rows) . ' file berhasil dihapus.', 'success');
            return $this->redirectWithFilters($module, $ref, $page);
        } catch (Throwable) {
            toast_add('Gagal menghapus file terpilih.', 'error');
            return $this->redirectWithFilters($module, $ref, $page);
        }
    }

    public function createModule(Request $request): Response
    {
        $module = $this->normalizeModule((string) $request->input('module', ''));
        if ($module === '') {
            toast_add('Nama modul tidak valid.', 'error');
            return Response::redirect('/filemanager');
        }

        $moduleDir = app()->basePath('storage/filemanager/' . $module);
        if (!is_dir($moduleDir) && !mkdir($moduleDir, 0775, true) && !is_dir($moduleDir)) {
            toast_add('Gagal membuat folder modul.', 'error');
            return Response::redirect('/filemanager');
        }

        toast_add('Modul berhasil ditambahkan.', 'success');
        return Response::redirect('/filemanager?module=' . urlencode($module));
    }

    public function deleteModule(Request $request): Response
    {
        $module = $this->normalizeModule((string) $request->input('module', ''));
        if ($module === '') {
            toast_add('Modul tidak valid.', 'error');
            return Response::redirect('/filemanager');
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM filemanager WHERE module = :module AND deleted_at IS NULL');
            $stmt->execute(['module' => $module]);
            $activeFiles = (int) $stmt->fetchColumn();
            if ($activeFiles > 0) {
                toast_add('Modul tidak bisa dihapus karena masih memiliki file aktif.', 'warning');
                return Response::redirect('/filemanager?module=' . urlencode($module));
            }
        } catch (Throwable) {
            toast_add('Gagal memeriksa data modul.', 'error');
            return Response::redirect('/filemanager?module=' . urlencode($module));
        }

        $moduleDir = app()->basePath('storage/filemanager/' . $module);
        if (is_dir($moduleDir) && !$this->deleteDirectoryIfEmpty($moduleDir)) {
            toast_add('Folder modul tidak kosong. Hapus file di dalamnya terlebih dahulu.', 'warning');
            return Response::redirect('/filemanager?module=' . urlencode($module));
        }

        toast_add('Modul berhasil dihapus.', 'success');
        return Response::redirect('/filemanager');
    }

    private function normalizeModule(string $module): string
    {
        $value = strtolower(trim($module));
        $value = preg_replace('/[^a-z0-9_-]/', '', $value);
        return is_string($value) ? substr($value, 0, 64) : '';
    }

    private function normalizeRefId(string $refId): string
    {
        $value = strtolower(trim($refId));
        $value = preg_replace('/[^a-z0-9_-]/', '', $value);
        return is_string($value) ? substr($value, 0, 64) : '';
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

    /**
     * @param array<string,mixed> $allFiles
     * @return array<int, array<string, mixed>>
     */
    private function collectUploadedFiles(array $allFiles, string $field): array
    {
        $raw = $allFiles[$field] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        $names = $raw['name'] ?? null;
        $tmpNames = $raw['tmp_name'] ?? null;
        $errors = $raw['error'] ?? null;
        $sizes = $raw['size'] ?? null;

        if (is_array($names)) {
            $files = [];
            $count = count($names);
            for ($i = 0; $i < $count; $i++) {
                $files[] = [
                    'name' => (string) ($names[$i] ?? ''),
                    'tmp_name' => (string) ($tmpNames[$i] ?? ''),
                    'error' => (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int) ($sizes[$i] ?? 0),
                ];
            }
            return $files;
        }

        return [[
            'name' => (string) ($raw['name'] ?? ''),
            'tmp_name' => (string) ($raw['tmp_name'] ?? ''),
            'error' => (int) ($raw['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($raw['size'] ?? 0),
        ]];
    }

    /**
     * @return array<int,string>
     */
    private function collectModules(\PDO $pdo, int $authId, bool $isAdmin): array
    {
        $modules = [];

        if ($isAdmin) {
            $stmt = $pdo->query('SELECT DISTINCT module FROM filemanager WHERE deleted_at IS NULL ORDER BY module ASC');
            $rows = $stmt !== false ? $stmt->fetchAll() : [];
        } else {
            $stmt = $pdo->prepare('SELECT DISTINCT module FROM filemanager WHERE deleted_at IS NULL AND uploaded_by = :uploaded_by ORDER BY module ASC');
            $stmt->execute(['uploaded_by' => $authId]);
            $rows = $stmt->fetchAll();
        }
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $module = $this->normalizeModule((string) ($row['module'] ?? ''));
                if ($module !== '') {
                    $modules[$module] = $module;
                }
            }
        }

        if ($isAdmin) {
            $storageBase = app()->basePath('storage/filemanager');
            if (is_dir($storageBase)) {
                $dirs = scandir($storageBase);
                if (is_array($dirs)) {
                    foreach ($dirs as $dir) {
                        if ($dir === '.' || $dir === '..') {
                            continue;
                        }
                        $fullPath = $storageBase . DIRECTORY_SEPARATOR . $dir;
                        if (!is_dir($fullPath)) {
                            continue;
                        }
                        $module = $this->normalizeModule((string) $dir);
                        if ($module !== '') {
                            $modules[$module] = $module;
                        }
                    }
                }
            }
        }

        ksort($modules);
        return array_values($modules);
    }

    private function isAdministrator(array $auth): bool
    {
        $role = strtolower(trim((string) ($auth['role'] ?? '')));
        if ($role === '') {
            return false;
        }

        return in_array($role, ['administrator', 'admin', 'superadmin', 'super-admin'], true);
    }

    private function deleteDirectoryIfEmpty(string $directory): bool
    {
        $entries = scandir($directory);
        if (!is_array($entries)) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                if (!$this->deleteDirectoryIfEmpty($path)) {
                    return false;
                }
                continue;
            }

            return false;
        }

        return @rmdir($directory);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function deleteFileRows(\PDO $pdo, array $rows): void
    {
        $delete = $pdo->prepare('UPDATE filemanager SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $delete->execute(['id' => $id]);

            $relative = ltrim((string) ($row['path'] ?? ''), '/');
            if ($relative !== '' && str_starts_with($relative, 'filemanager/')) {
                $fullPath = app()->basePath('storage/' . str_replace('/', DIRECTORY_SEPARATOR, $relative));
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }
    }

    private function redirectWithFilters(string $module, string $ref, int $page): Response
    {
        $query = [];
        if ($module !== '') {
            $query['module'] = $module;
        }
        if ($ref !== '') {
            $query['ref'] = $ref;
        }
        if ($page > 1) {
            $query['page'] = (string) $page;
        }

        $url = '/filemanager';
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return Response::redirect($url);
    }
}
