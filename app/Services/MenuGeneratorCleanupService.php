<?php

declare(strict_types=1);

namespace App\Services;

class MenuGeneratorCleanupService
{
    /**
     * @param array<int, array<string, mixed>> $files
     * @return array{deleted_count: int, deleted_files: array<int, string>}
     */
    public function cleanupGeneratedFiles(array $files): array
    {
        $deletedCount = 0;
        $deletedFiles = [];

        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $relativePath = trim((string) ($file['file_path'] ?? ''));
            if ($relativePath === '') {
                continue;
            }

            $absolutePath = app()->basePath($relativePath);
            $normalized = str_replace('\\', '/', $absolutePath);
            $basePath = str_replace('\\', '/', app()->basePath(''));
            if (!str_starts_with($normalized, $basePath . '/')) {
                continue;
            }

            if (is_file($absolutePath) && @unlink($absolutePath)) {
                $deletedCount++;
                $deletedFiles[] = $relativePath;
                $this->cleanupEmptyParents(dirname($absolutePath), $basePath);
            }
        }

        return [
            'deleted_count' => $deletedCount,
            'deleted_files' => $deletedFiles,
        ];
    }

    private function cleanupEmptyParents(string $directory, string $basePath): void
    {
        $directory = str_replace('\\', '/', $directory);
        $basePath = rtrim(str_replace('\\', '/', $basePath), '/');

        while ($directory !== '' && str_starts_with($directory, $basePath . '/')) {
            if (!is_dir($directory)) {
                $directory = dirname($directory);
                $directory = str_replace('\\', '/', $directory);
                continue;
            }

            $entries = scandir($directory);
            if (!is_array($entries)) {
                return;
            }

            $items = array_values(array_filter($entries, static fn (string $item): bool => !in_array($item, ['.', '..'], true)));
            if ($items !== []) {
                return;
            }

            @rmdir($directory);
            $directory = dirname($directory);
            $directory = str_replace('\\', '/', $directory);
        }
    }
}
