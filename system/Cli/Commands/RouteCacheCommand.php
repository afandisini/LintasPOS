<?php

declare(strict_types=1);

namespace System\Cli\Commands;

use System\Cli\Command;
use System\Foundation\Application;
use System\Support\FileSystem;

class RouteCacheCommand extends Command
{
    public function name(): string
    {
        return 'route:cache';
    }

    public function description(): string
    {
        return 'Create cached route artifact';
    }

    public function handle(array $args, Application $app): int
    {
        $cachePath = $this->cacheFilePath($app);
        FileSystem::ensureDir(dirname($cachePath));

        $_ENV['AITI_SKIP_ROUTE_CACHE'] = '1';
        putenv('AITI_SKIP_ROUTE_CACHE=1');
        $freshApp = require $app->basePath('bootstrap/app.php');
        unset($_ENV['AITI_SKIP_ROUTE_CACHE']);
        putenv('AITI_SKIP_ROUTE_CACHE');

        $export = $freshApp->router()->exportCacheableRoutes();
        $uncacheable = $export['uncacheable'];

        if ($uncacheable !== []) {
            fwrite(STDOUT, "[ERR] Route cache cannot be created because closure routes exist:" . PHP_EOL);
            foreach ($uncacheable as $entry) {
                fwrite(STDOUT, '  - ' . $entry . PHP_EOL);
            }
            fwrite(STDOUT, "Use controller actions instead of closures, then run `php aiti route:cache` again." . PHP_EOL);
            return 1;
        }

        $payload = "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . var_export($export['routes'], true)
            . ";\n";

        file_put_contents($cachePath, $payload);
        fwrite(STDOUT, '[OK] Route cache created: ' . $cachePath . PHP_EOL);
        return 0;
    }

    private function cacheFilePath(Application $app): string
    {
        return FileSystem::joinPath($this->storagePath($app), 'cache', 'routes.php');
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

