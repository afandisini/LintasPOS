<?php

declare(strict_types=1);

namespace System\Support;

final class FileSystem
{
    public static function ensureDir(string $dir): void
    {
        if ($dir === '') {
            return;
        }

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create directory: ' . $dir);
        }
    }

    public static function deleteFile(string $file): bool
    {
        if (!file_exists($file)) {
            return false;
        }

        if (!is_file($file)) {
            throw new \RuntimeException('Path is not a file: ' . $file);
        }

        if (!@unlink($file)) {
            throw new \RuntimeException('Unable to delete file: ' . $file);
        }

        return true;
    }

    public static function clearDir(string $dir, bool $keepDir = true): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $items = scandir($dir);
        if ($items === false) {
            throw new \RuntimeException('Unable to read directory: ' . $dir);
        }

        $removed = 0;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = self::joinPath($dir, $item);
            if (is_dir($path)) {
                $removed += self::clearDir($path, false);
                if (!@rmdir($path)) {
                    throw new \RuntimeException('Unable to delete directory: ' . $path);
                }
                continue;
            }

            if (!@unlink($path)) {
                throw new \RuntimeException('Unable to delete file: ' . $path);
            }
            $removed++;
        }

        if (!$keepDir) {
            if (!@rmdir($dir)) {
                throw new \RuntimeException('Unable to delete directory: ' . $dir);
            }
        }

        return $removed;
    }

    public static function joinPath(string ...$parts): string
    {
        $clean = [];

        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }

            $part = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $part);
            if ($index === 0) {
                $clean[] = rtrim($part, DIRECTORY_SEPARATOR);
                continue;
            }

            $clean[] = trim($part, DIRECTORY_SEPARATOR);
        }

        return implode(DIRECTORY_SEPARATOR, $clean);
    }
}
