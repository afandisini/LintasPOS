<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\ApiResponse;
use App\Services\Database;
use System\Http\Request;
use System\Http\Response;
use Throwable;

final class MediaController
{
    private const MODULES = ['barang', 'jasa', 'pelanggan', 'supplier', 'users', 'toko', 'general'];
    private const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'application/pdf' => 'pdf'];

    public function store(Request $request): Response
    {
        $user = $request->attribute('api_user');
        $module = strtolower(trim((string) $request->input('module', '')));
        $refId = trim((string) $request->input('ref_id', ''));
        $visibility = strtolower(trim((string) $request->input('visibility', 'private')));
        $file = $_FILES['file'] ?? $_FILES['upload_file'] ?? null;
        if (!is_array($user) || !in_array($module, self::MODULES, true) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $refId)) return ApiResponse::error('VALIDATION_ERROR', 'Module dan ref_id wajib valid.', 422);
        if ($visibility !== 'public') $visibility = 'private';
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ApiResponse::error('VALIDATION_ERROR', 'File wajib diupload.', 422);
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0 || $size > 5 * 1024 * 1024) return ApiResponse::error('VALIDATION_ERROR', 'File maksimal 5 MB dan harus berupa upload valid.', 422);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!is_string($mime) || !isset(self::MIMES[$mime])) return ApiResponse::error('VALIDATION_ERROR', 'Tipe file tidak diizinkan.', 422);
        $extension = self::MIMES[$mime];
        $filename = 'api_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $relative = 'filemanager/' . $module . '/' . $refId . '/' . $filename;
        $targetDir = app()->basePath('storage/filemanager/' . $module . '/' . $refId);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) return ApiResponse::error('SERVER_ERROR', 'Gagal membuat direktori media.', 500);
        $target = $targetDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmp, $target)) return ApiResponse::error('SERVER_ERROR', 'Gagal menyimpan media.', 500);
        try {
            $stmt = Database::connection()->prepare('INSERT INTO filemanager (`name`, module, ref_id, path, filename, mime_type, extension, size_bytes, visibility, uploaded_by, checksum_sha1, create_time, created_at, updated_at) VALUES (:name,:module,:ref_id,:path,:filename,:mime,:extension,:size,:visibility,:uploaded_by,:checksum,NOW(),NOW(),NOW())');
            $stmt->execute(['name' => (string) ($file['name'] ?? $filename), 'module' => $module, 'ref_id' => $refId, 'path' => $relative, 'filename' => $filename, 'mime' => $mime, 'extension' => $extension, 'size' => $size, 'visibility' => $visibility, 'uploaded_by' => (int) ($user['user_id'] ?? 0), 'checksum' => sha1_file($target) ?: null]);
            $id = (int) Database::connection()->lastInsertId();
            return ApiResponse::success(['id' => $id, 'module' => $module, 'ref_id' => $refId, 'path' => $relative, 'url' => '/media/' . $id, 'mime_type' => $mime, 'size_bytes' => $size], 201);
        } catch (Throwable) {
            @unlink($target);
            return ApiResponse::error('SERVER_ERROR', 'Gagal menyimpan metadata media.', 500);
        }
    }

    public function show(Request $request, string $id): Response
    {
        try {
            $stmt = Database::connection()->prepare('SELECT id, name, module, ref_id, path, mime_type, extension, size_bytes, visibility, uploaded_by, created_at FROM filemanager WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['id' => (int) $id]);
            $row = $stmt->fetch();
            if (!is_array($row)) return ApiResponse::error('NOT_FOUND', 'Media tidak ditemukan.', 404);
            $role = strtolower((string) ($user['role'] ?? ''));
            if ((string) ($row['visibility'] ?? 'private') === 'private' && (int) ($row['uploaded_by'] ?? 0) !== (int) ($user['user_id'] ?? 0) && !in_array($role, ['administrator', 'admin', 'owner'], true)) return ApiResponse::error('FORBIDDEN', 'Anda tidak memiliki izin melihat media ini.', 403);
            return ApiResponse::success($row);
        } catch (Throwable) {
            return ApiResponse::error('SERVER_ERROR', 'Gagal memuat media.', 500);
        }
    }

    public function destroy(Request $request, string $id): Response
    {
        $user = $request->attribute('api_user');
        try {
            $stmt = Database::connection()->prepare('SELECT uploaded_by FROM filemanager WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['id' => (int) $id]);
            $owner = $stmt->fetchColumn();
            $role = strtolower((string) ($user['role'] ?? ''));
            if ($owner === false) return ApiResponse::error('NOT_FOUND', 'Media tidak ditemukan.', 404);
            if ((int) $owner !== (int) ($user['user_id'] ?? 0) && !in_array($role, ['administrator', 'admin', 'owner'], true)) return ApiResponse::error('FORBIDDEN', 'Anda tidak memiliki izin menghapus media ini.', 403);
            $delete = Database::connection()->prepare('UPDATE filemanager SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
            $delete->execute(['id' => (int) $id]);
            return ApiResponse::success(null);
        } catch (Throwable) {
            return ApiResponse::error('SERVER_ERROR', 'Gagal menghapus media.', 500);
        }
    }
}
