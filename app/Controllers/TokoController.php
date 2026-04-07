<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use System\Http\Request;
use System\Http\Response;
use Throwable;

class TokoController
{
    public function index(Request $request): Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $store = [];

        try {
            $pdo = Database::connection();
            $hasIconsColumn = $this->hasTokoIconsColumn($pdo);
            $select = 'SELECT id, nama_toko, alamat_toko, tlp, nama_pemilik, logo';
            if ($hasIconsColumn) {
                $select .= ', icons';
            }
            $select .= ' FROM toko ORDER BY id ASC LIMIT 1';
            $stmt = $pdo->query($select);
            $row = $stmt->fetch();
            if (is_array($row)) {
                $store = $row;
            }
        } catch (Throwable) {
            toast_add('Gagal memuat data toko.', 'error');
        }

        if ($store === []) {
            $fallback = store_profile();
            $store = [
                'id' => 1,
                'nama_toko' => (string) ($fallback['nama_toko'] ?? ''),
                'alamat_toko' => (string) ($fallback['alamat_toko'] ?? ''),
                'tlp' => (string) ($fallback['tlp'] ?? ''),
                'nama_pemilik' => (string) ($fallback['nama_pemilik'] ?? ''),
                'logo' => null,
                'icons' => (string) ($fallback['icons'] ?? ''),
            ];
        }

        $html = app()->view()->render('toko/index', [
            'title' => 'Pengaturan Toko',
            'auth' => $auth,
            'activeMenu' => 'toko',
            'store' => $store,
        ]);

        return Response::html($html);
    }

    public function update(Request $request): Response
    {
        $storeId = (int) $request->input('id', '0');
        $requestedBrandName = trim((string) $request->input('nama_toko', ''));
        $namaToko = enforce_brand_name($requestedBrandName);
        $alamatToko = trim((string) $request->input('alamat_toko', ''));
        $telepon = trim((string) $request->input('tlp', ''));
        $namaPemilik = trim((string) $request->input('nama_pemilik', ''));
        $iconClass = trim((string) $request->input('icons', ''));
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $authId = (int) ($auth['id'] ?? 0);

        if ($requestedBrandName !== '' && strcasecmp($requestedBrandName, brand_name()) !== 0) {
            toast_add('Nama brand dikunci dan tidak dapat diubah.', 'warning');
        }

        if ($storeId <= 0) {
            toast_add('Data toko tidak valid.', 'error');
            return Response::redirect('/toko');
        }

        if ($alamatToko === '' || $telepon === '' || $namaPemilik === '') {
            toast_add('Alamat, telepon, dan nama pemilik wajib diisi.', 'error');
            return Response::redirect('/toko');
        }

        $namaToko = substr($namaToko, 0, 255);
        $telepon = substr($telepon, 0, 255);
        $namaPemilik = substr($namaPemilik, 0, 255);
        $iconClass = substr($iconClass, 0, 255);

        if ($iconClass !== '' && !preg_match('/^bi bi-[a-z0-9-]+$/', $iconClass)) {
            toast_add('Format icon tidak valid.', 'error');
            return Response::redirect('/toko');
        }

        $newLogoAbsolutePath = '';
        $newLogoFilemanagerId = 0;

        try {
            $pdo = Database::connection();
            $hasIconsColumn = $this->hasTokoIconsColumn($pdo);
            if (!$hasIconsColumn && $iconClass !== '') {
                toast_add('Kolom toko.icons belum tersedia. Jalankan migration add_icons_to_toko agar icon tersimpan.', 'warning');
            }

            $selectExisting = 'SELECT id, logo';
            if ($hasIconsColumn) {
                $selectExisting .= ', icons';
            }
            $selectExisting .= ' FROM toko WHERE id = :id LIMIT 1';
            $exists = $pdo->prepare($selectExisting);
            $exists->execute(['id' => $storeId]);
            $existingStore = $exists->fetch();
            $hasExisting = is_array($existingStore);

            $params = [
                'id' => $storeId,
                'nama_toko' => $namaToko,
                'alamat_toko' => $alamatToko,
                'tlp' => $telepon,
                'nama_pemilik' => $namaPemilik,
            ];
            $setParts = [
                'nama_toko = :nama_toko',
                'alamat_toko = :alamat_toko',
                'tlp = :tlp',
                'nama_pemilik = :nama_pemilik',
            ];
            if ($hasIconsColumn) {
                $setParts[] = 'icons = :icons';
                $params['icons'] = $iconClass !== '' ? $iconClass : null;
            }

            $logoFileId = null;
            $uploadedLogo = $_FILES['logo_file'] ?? null;
            if (is_array($uploadedLogo) && (int) ($uploadedLogo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ((int) ($uploadedLogo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    toast_add('Upload logo tidak valid.', 'error');
                    return Response::redirect('/toko');
                }

                $tmp = (string) ($uploadedLogo['tmp_name'] ?? '');
                $originalName = trim((string) ($uploadedLogo['name'] ?? ''));
                $size = (int) ($uploadedLogo['size'] ?? 0);
                if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0) {
                    toast_add('File logo gagal diproses.', 'error');
                    return Response::redirect('/toko');
                }

                if ($size > 5_242_880) {
                    toast_add('Ukuran logo melebihi batas 5MB.', 'error');
                    return Response::redirect('/toko');
                }

                $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
                if (!in_array($extension, $allowedExtensions, true)) {
                    toast_add('Format logo harus JPG, PNG, WEBP, GIF, atau SVG.', 'error');
                    return Response::redirect('/toko');
                }

                $mimeType = (string) mime_content_type($tmp);
                $allowedSvgMimeTypes = ['image/svg+xml', 'text/xml', 'application/xml'];
                $isSvgMime = in_array(strtolower($mimeType), $allowedSvgMimeTypes, true);
                if (!str_starts_with($mimeType, 'image/') && !($extension === 'svg' && $isSvgMime)) {
                    toast_add('File logo harus berupa gambar.', 'error');
                    return Response::redirect('/toko');
                }

                $baseStorage = app()->basePath('storage/filemanager/toko/' . $storeId);
                if (!is_dir($baseStorage) && !mkdir($baseStorage, 0775, true) && !is_dir($baseStorage)) {
                    toast_add('Gagal membuat direktori logo toko.', 'error');
                    return Response::redirect('/toko');
                }

                $storedName = 'logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                $targetPath = $baseStorage . DIRECTORY_SEPARATOR . $storedName;
                if (!move_uploaded_file($tmp, $targetPath)) {
                    toast_add('Gagal menyimpan logo ke storage.', 'error');
                    return Response::redirect('/toko');
                }
                $newLogoAbsolutePath = $targetPath;

                $logoPath = 'filemanager/toko/' . $storeId . '/' . $storedName;
                $logoFileId = $this->insertFilemanagerRecord(
                    $pdo,
                    $authId > 0 ? $authId : null,
                    $originalName !== '' ? $originalName : $storedName,
                    'toko',
                    (string) $storeId,
                    $logoPath,
                    $storedName,
                    $mimeType,
                    $extension,
                    $size
                );
                if ($logoFileId <= 0) {
                    @unlink($targetPath);
                    toast_add('Gagal menyimpan metadata logo.', 'error');
                    return Response::redirect('/toko');
                }

                $newLogoFilemanagerId = $logoFileId;
                $setParts[] = 'logo = :logo';
                $params['logo'] = $logoFileId;
            }

            if ($hasExisting) {
                $sql = 'UPDATE toko SET ' . implode(', ', $setParts) . ' WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                $insertCols = 'id, nama_toko, alamat_toko, tlp, nama_pemilik, logo';
                $insertVals = ':id, :nama_toko, :alamat_toko, :tlp, :nama_pemilik, :logo';
                $insertParams = [
                    'id' => $storeId,
                    'nama_toko' => $namaToko,
                    'alamat_toko' => $alamatToko,
                    'tlp' => $telepon,
                    'nama_pemilik' => $namaPemilik,
                    'logo' => $logoFileId,
                ];
                if ($hasIconsColumn) {
                    $insertCols .= ', icons';
                    $insertVals .= ', :icons';
                    $insertParams['icons'] = $iconClass !== '' ? $iconClass : null;
                }
                $insert = $pdo->prepare(
                    'INSERT INTO toko (' . $insertCols . ') VALUES (' . $insertVals . ')'
                );
                $insert->execute($insertParams);
            }

            if ($logoFileId !== null && $hasExisting) {
                $oldLogoId = (int) ($existingStore['logo'] ?? 0);
                if ($oldLogoId > 0) {
                    $this->softDeleteFilemanagerById($pdo, $oldLogoId);
                }
            }

            toast_add('Pengaturan toko berhasil diperbarui.', 'success');
        } catch (Throwable) {
            if ($newLogoFilemanagerId > 0) {
                try {
                    $pdoRollback = Database::connection();
                    $this->softDeleteFilemanagerById($pdoRollback, $newLogoFilemanagerId);
                } catch (Throwable) {
                    // no-op
                }
            }
            if ($newLogoAbsolutePath !== '' && is_file($newLogoAbsolutePath)) {
                @unlink($newLogoAbsolutePath);
            }
            toast_add('Gagal memperbarui data toko.', 'error');
        }

        return Response::redirect('/toko');
    }

    private function hasTokoIconsColumn(\PDO $pdo): bool
    {
        static $cached = null;
        if (is_bool($cached)) {
            return $cached;
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM toko LIKE 'icons'");
            $row = $stmt->fetch();
            $cached = is_array($row);
            return $cached;
        } catch (Throwable) {
            $cached = false;
            return false;
        }
    }

    private function insertFilemanagerRecord(
        \PDO $pdo,
        ?int $uploadedBy,
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
            'uploaded_by' => $uploadedBy !== null && $uploadedBy > 0 ? $uploadedBy : null,
            'checksum_sha1' => null,
        ]);

        return (int) $pdo->lastInsertId();
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
}
