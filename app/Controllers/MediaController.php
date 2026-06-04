<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use System\Http\Request;
use System\Http\Response;
use Throwable;

class MediaController
{
    public function show(Request $request): Response
    {
        $rawPath = (string) $request->input('path', '');
        return $this->serveFile($rawPath, false);
    }

    public function showById(Request $request, string $id): Response
    {
        $relative = $this->pathById((int) $id);
        if ($relative === '') {
            return Response::html('Not Found', 404);
        }

        return $this->serveRelativePath($relative, false);
    }

    public function showPublic(Request $request): Response
    {
        $rawPath = (string) $request->input('path', '');
        $relative = $this->normalizeRelativePath($rawPath);
        if (!str_starts_with($relative, 'filemanager/toko/')) {
            return Response::html('Forbidden', 403);
        }
        return $this->serveRelativePath($relative, true);
    }

    public function showPublicById(Request $request, string $id): Response
    {
        $relative = $this->pathById((int) $id);
        if ($relative === '' || !str_starts_with($relative, 'filemanager/toko/')) {
            return Response::html('Forbidden', 403);
        }

        return $this->serveRelativePath($relative, true);
    }

    private function serveFile(string $rawPath, bool $skipAuth): Response
    {
        $relative = $this->normalizeRelativePath($rawPath);
        if ($relative === '' || str_contains($relative, '..') || !str_starts_with($relative, 'filemanager/')) {
            return Response::html('Not Found', 404);
        }

        return $this->serveRelativePath($relative, $skipAuth);
    }

    private function serveRelativePath(string $relative, bool $skipAuth): Response
    {
        $relative = $this->normalizeRelativePath($relative);
        if ($relative === '' || str_contains($relative, '..') || !str_starts_with($relative, 'filemanager/')) {
            return Response::html('Not Found', 404);
        }

        $storageRoot = app()->basePath('storage');
        $storageRootReal = realpath($storageRoot);
        if ($storageRootReal === false) {
            return Response::html('Not Found', 404);
        }

        $fullPath = $storageRootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $fullPathReal = realpath($fullPath);
        if ($fullPathReal === false || !is_file($fullPathReal)) {
            return Response::html('Not Found', 404);
        }

        $storageRootNorm = str_replace('\\', '/', $storageRootReal);
        $fullPathNorm = str_replace('\\', '/', $fullPathReal);
        if (!str_starts_with($fullPathNorm, $storageRootNorm . '/')) {
            return Response::html('Not Found', 404);
        }

        if (str_starts_with($relative, 'filemanager/barang/')) {
            if (!$this->canAccessBarangMedia($relative)) {
                return Response::html('Forbidden', 403);
            }
        }

        $content = file_get_contents($fullPathReal);
        if (!is_string($content)) {
            return Response::html('Not Found', 404);
        }

        $mime = $this->detectMimeType($fullPathReal);
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }

        $size = filesize($fullPathReal);
        $headers = [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=86400',
        ];
        if ($size !== false) {
            $headers['Content-Length'] = (string) $size;
        }

        return new Response($content, 200, $headers);
    }

    private function normalizeRelativePath(string $path): string
    {
        $relative = ltrim(str_replace('\\', '/', trim($path)), '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, 8);
        }

        return $relative;
    }

    private function pathById(int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT path FROM filemanager WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['id' => $id]);
            $path = $stmt->fetchColumn();
            if (is_string($path)) {
                $relative = $this->normalizeRelativePath($path);
                if ($relative !== '' && str_starts_with($relative, 'filemanager/')) {
                    return $relative;
                }
            }
        } catch (Throwable) {
            return '';
        }

        return '';
    }

    private function canAccessBarangMedia(string $relativePath): bool
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $authId = (int) ($auth['id'] ?? 0);
        $isAdmin = $this->isAdministrator($auth);
        if ($isAdmin) {
            return true;
        }
        if ($authId <= 0) {
            return false;
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT uploaded_by FROM filemanager WHERE deleted_at IS NULL AND path = :path AND module = :module LIMIT 1'
            );
            $stmt->execute([
                'path' => $relativePath,
                'module' => 'barang',
            ]);
            $row = $stmt->fetch();
            if (!is_array($row)) {
                return false;
            }
            return (int) ($row['uploaded_by'] ?? 0) === $authId;
        } catch (Throwable) {
            return false;
        }
    }

    private function isAdministrator(array $auth): bool
    {
        $role = strtolower(trim((string) ($auth['role'] ?? '')));
        if ($role === '') {
            return false;
        }

        return in_array($role, ['administrator', 'admin', 'superadmin', 'super-admin'], true);
    }

    private function detectMimeType(string $path): string
    {
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $mime = '';
        if (class_exists(\finfo::class)) {
            try {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $detected = $finfo->file($path);
                if (is_string($detected)) {
                    $mime = trim($detected);
                }
            } catch (Throwable) {
                $mime = '';
            }
        }

        if ($mime === '' && function_exists('mime_content_type')) {
            try {
                $detected = mime_content_type($path);
                if (is_string($detected)) {
                    $mime = trim($detected);
                }
            } catch (Throwable) {
                $mime = '';
            }
        }

        return $mime;
    }
}
