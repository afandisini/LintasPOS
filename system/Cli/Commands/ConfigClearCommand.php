<?php

declare(strict_types=1);

namespace System\Cli\Commands;

use System\Cli\Command;
use System\Foundation\Application;
use System\Support\FileSystem;

class ConfigClearCommand extends Command
{
    public function name(): string
    {
        return 'config:clear';
    }

    public function description(): string
    {
        return 'Clear cached config artifact';
    }

    public function handle(array $args, Application $app): int
    {
        $target = FileSystem::joinPath($this->cachePath($app), 'config.php');

        if (!file_exists($target)) {
            fwrite(STDOUT, '[SKIP] Config cache not found. Nothing to clear.' . PHP_EOL);
            return 0;
        }

        try {
            FileSystem::deleteFile($target);
        } catch (\RuntimeException $exception) {
            fwrite(STDOUT, '[ERR] Failed to clear config cache: ' . $exception->getMessage() . PHP_EOL);
            return 1;
        }

        fwrite(STDOUT, '[OK] Config cache cleared.' . PHP_EOL);
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
