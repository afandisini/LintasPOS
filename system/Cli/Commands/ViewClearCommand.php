<?php

declare(strict_types=1);

namespace System\Cli\Commands;

use System\Cli\Command;
use System\Foundation\Application;
use System\Support\FileSystem;

class ViewClearCommand extends Command
{
    public function name(): string
    {
        return 'view:clear';
    }

    public function description(): string
    {
        return 'Clear compiled view cache files';
    }

    public function handle(array $args, Application $app): int
    {
        $viewCacheDir = FileSystem::joinPath($this->cachePath($app), 'views');

        if (!is_dir($viewCacheDir)) {
            fwrite(STDOUT, '[SKIP] View cache directory not found. Nothing to clear.' . PHP_EOL);
            return 0;
        }

        try {
            $removed = FileSystem::clearDir($viewCacheDir, true);
        } catch (\RuntimeException $exception) {
            fwrite(STDOUT, '[ERR] Failed to clear view cache: ' . $exception->getMessage() . PHP_EOL);
            return 1;
        }

        fwrite(STDOUT, '[OK] View cache cleared. Removed: ' . $removed . ' file(s).' . PHP_EOL);
        return 0;
    }

    private function cachePath(Application $app): string
    {
        return FileSystem::joinPath($this->storagePath($app), 'cache');
    }

    private function storagePath(Application $app): string
    {
        $storage = (string) $app->config()->get('paths.storage', 'storage');
        if ($this->isAbsolutePath($storage)) {
            return $storage;
        }

        return FileSystem::joinPath($app->basePath(), $storage);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || str_starts_with($path, '\\\\')
            || (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
