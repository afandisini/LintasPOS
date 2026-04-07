<?php

declare(strict_types=1);

namespace System\Cli\Commands;

use System\Cli\Command;
use System\Foundation\Application;

class PresetBootstrapCommand extends Command
{
    /**
     * @var array<string, string>
     */
    private const TARGET_MAP = [
        'bootstrap.min.css' => 'public/assets/vendor/bootstrap/bootstrap.min.css',
        'bootstrap.bundle.min.js' => 'public/assets/vendor/bootstrap/bootstrap.bundle.min.js',
        'bootstrap-icons.min.css' => 'public/assets/vendor/bootstrap-icons/bootstrap-icons.min.css',
    ];

    public function name(): string
    {
        return 'preset:bootstrap';
    }

    public function description(): string
    {
        return 'Copy local Bootstrap and Bootstrap Icons assets to public/assets/vendor';
    }

    public function handle(array $args, Application $app): int
    {
        $base = $app->basePath();
        $sourceOption = $this->readSourceOption($args);
        $source = $this->resolveSource($base, $sourceOption);

        if ($source === null) {
            fwrite(STDOUT, "Bootstrap preset source not found.\n");
            fwrite(STDOUT, "Expected internal preset at: system/Presets/bootstrap\n");
            fwrite(STDOUT, "Fallback source: node_modules (bootstrap + bootstrap-icons)\n");
            fwrite(STDOUT, "Fix options:\n");
            fwrite(STDOUT, "1) Restore preset files in system/Presets/bootstrap\n");
            fwrite(STDOUT, "2) Run npm install then php aiti preset:bootstrap\n");
            return 1;
        }

        $this->ensureTargetDirectories($base);
        $copied = $this->copyPreset($base, $source['type'], $source['root']);

        fwrite(STDOUT, 'Bootstrap preset installed from ' . $source['type'] . " source.\n");
        foreach ($copied as $item) {
            fwrite(STDOUT, ' - ' . $item . "\n");
        }

        if ($source['type'] === 'node') {
            fwrite(STDOUT, "Tip: commit system/Presets/bootstrap for clone-ready preset installs.\n");
        }

        return 0;
    }

    private function readSourceOption(array $args): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--source=')) {
                $source = strtolower(trim(substr($arg, 9)));
                if (in_array($source, ['internal', 'node'], true)) {
                    return $source;
                }
            }
        }

        return null;
    }

    /**
     * @return array{type: string, root: string}|null
     */
    private function resolveSource(string $base, ?string $sourceOption): ?array
    {
        $internal = [
            'type' => 'internal',
            'root' => $base . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'Presets' . DIRECTORY_SEPARATOR . 'bootstrap',
        ];
        $node = [
            'type' => 'node',
            'root' => $base . DIRECTORY_SEPARATOR . 'node_modules',
        ];

        if ($sourceOption === 'internal') {
            return $this->isInternalPresetReady($internal['root']) ? $internal : null;
        }

        if ($sourceOption === 'node') {
            return $this->isNodePresetReady($node['root']) ? $node : null;
        }

        if ($this->isInternalPresetReady($internal['root'])) {
            return $internal;
        }

        if ($this->isNodePresetReady($node['root'])) {
            return $node;
        }

        return null;
    }

    private function isInternalPresetReady(string $root): bool
    {
        $files = [
            $root . DIRECTORY_SEPARATOR . 'bootstrap.min.css',
            $root . DIRECTORY_SEPARATOR . 'bootstrap.bundle.min.js',
            $root . DIRECTORY_SEPARATOR . 'bootstrap-icons.min.css',
        ];

        foreach ($files as $file) {
            if (!is_file($file)) {
                return false;
            }
        }

        $fonts = $root . DIRECTORY_SEPARATOR . 'fonts';
        return $this->directoryHasFiles($fonts);
    }

    private function isNodePresetReady(string $root): bool
    {
        $files = [
            $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'bootstrap.min.css',
            $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'bootstrap.bundle.min.js',
            $root . DIRECTORY_SEPARATOR . 'bootstrap-icons' . DIRECTORY_SEPARATOR . 'font' . DIRECTORY_SEPARATOR . 'bootstrap-icons.min.css',
        ];

        foreach ($files as $file) {
            if (!is_file($file)) {
                return false;
            }
        }

        $fonts = $root . DIRECTORY_SEPARATOR . 'bootstrap-icons' . DIRECTORY_SEPARATOR . 'font' . DIRECTORY_SEPARATOR . 'fonts';
        return $this->directoryHasFiles($fonts);
    }

    private function directoryHasFiles(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        $items = scandir($directory);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (is_file($directory . DIRECTORY_SEPARATOR . $item)) {
                return true;
            }
        }

        return false;
    }

    private function ensureTargetDirectories(string $base): void
    {
        $directories = [
            $base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bootstrap',
            $base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bootstrap-icons',
            $base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bootstrap-icons' . DIRECTORY_SEPARATOR . 'fonts',
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function copyPreset(string $base, string $sourceType, string $sourceRoot): array
    {
        $copied = [];

        foreach (self::TARGET_MAP as $asset => $target) {
            $source = $this->resolveAssetPath($sourceType, $sourceRoot, $asset);
            $destination = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
            $this->copyFile($source, $destination);
            $copied[] = str_replace('\\', '/', $source) . ' -> ' . $target;
        }

        $sourceFonts = $this->resolveFontsPath($sourceType, $sourceRoot);
        $targetFonts = $base . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'bootstrap-icons' . DIRECTORY_SEPARATOR . 'fonts';
        $fontCopies = $this->copyDirectory($sourceFonts, $targetFonts);
        foreach ($fontCopies as $fontCopy) {
            $copied[] = $fontCopy;
        }

        return $copied;
    }

    private function resolveAssetPath(string $sourceType, string $sourceRoot, string $asset): string
    {
        if ($sourceType === 'internal') {
            return $sourceRoot . DIRECTORY_SEPARATOR . $asset;
        }

        return match ($asset) {
            'bootstrap.min.css' => $sourceRoot . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'bootstrap.min.css',
            'bootstrap.bundle.min.js' => $sourceRoot . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'bootstrap.bundle.min.js',
            default => $sourceRoot . DIRECTORY_SEPARATOR . 'bootstrap-icons' . DIRECTORY_SEPARATOR . 'font' . DIRECTORY_SEPARATOR . 'bootstrap-icons.min.css',
        };
    }

    private function resolveFontsPath(string $sourceType, string $sourceRoot): string
    {
        if ($sourceType === 'internal') {
            return $sourceRoot . DIRECTORY_SEPARATOR . 'fonts';
        }

        return $sourceRoot . DIRECTORY_SEPARATOR . 'bootstrap-icons' . DIRECTORY_SEPARATOR . 'font' . DIRECTORY_SEPARATOR . 'fonts';
    }

    private function copyFile(string $source, string $destination): void
    {
        if (!is_file($source)) {
            throw new \RuntimeException('Missing asset file: ' . $source);
        }

        $directory = dirname($destination);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        copy($source, $destination);
    }

    /**
     * @return array<int, string>
     */
    private function copyDirectory(string $source, string $destination): array
    {
        $copied = [];

        if (!is_dir($source)) {
            throw new \RuntimeException('Missing asset directory: ' . $source);
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0775, true);
        }

        $items = scandir($source);
        if ($items === false) {
            return $copied;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $from = $source . DIRECTORY_SEPARATOR . $item;
            $to = $destination . DIRECTORY_SEPARATOR . $item;
            if (is_dir($from)) {
                $copied = array_merge($copied, $this->copyDirectory($from, $to));
            } else {
                copy($from, $to);
                $copied[] = str_replace('\\', '/', $from) . ' -> '
                    . str_replace('\\', '/', str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $to));
            }
        }

        return $copied;
    }
}
