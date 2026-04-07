<?php

declare(strict_types=1);

namespace System\Cli\Commands;

use RuntimeException;
use System\Cli\Command;
use System\Foundation\Application;
use System\Upgrade\UpgradeManager;

class UpgradeApplyCommand extends Command
{
    public function name(): string
    {
        return 'upgrade:apply';
    }

    public function description(): string
    {
        return 'Apply framework upgrade patches safely (dry-run by default)';
    }

    public function handle(array $args, Application $app): int
    {
        $dryRun = !$this->hasFlag($args, '--apply');
        $force = $this->hasFlag($args, '--force');
        $manager = new UpgradeManager($app->basePath());

        try {
            $catalog = $manager->loadCatalog();
            $from = $this->argumentValue($args, 'from') ?? $manager->currentVersion();
            $target = $this->argumentValue($args, 'target')
                ?? $this->argumentValue($args, 'to')
                ?? (string) $catalog['latest'];
            $plan = $manager->buildPlan($catalog, $from, $target);
        } catch (RuntimeException $exception) {
            fwrite(STDOUT, '[ERR] ' . $exception->getMessage() . PHP_EOL);
            return 1;
        }

        fwrite(STDOUT, 'Upgrade Apply' . PHP_EOL);
        fwrite(STDOUT, '  From  : ' . $from . PHP_EOL);
        fwrite(STDOUT, '  Target: ' . $target . PHP_EOL);
        fwrite(STDOUT, '  Mode  : ' . ($dryRun ? 'dry-run' : 'apply') . PHP_EOL);
        fwrite(STDOUT, '  Force : ' . ($force ? 'yes' : 'no') . PHP_EOL);

        if ($plan === []) {
            fwrite(STDOUT, '[OK] No upgrade step required.' . PHP_EOL);
            return 0;
        }

        $conflicts = $manager->detectConflicts($plan);
        if ($conflicts['blocked_user_owned'] !== []) {
            fwrite(STDOUT, PHP_EOL . 'User-owned paths will be skipped:' . PHP_EOL);
            foreach ($conflicts['blocked_user_owned'] as $item) {
                fwrite(STDOUT, '  - ' . $item['path'] . PHP_EOL);
            }
        }

        if (!$force && $conflicts['modified_core'] !== []) {
            fwrite(STDOUT, PHP_EOL . 'Modified core files detected (will be skipped without --force):' . PHP_EOL);
            foreach ($conflicts['modified_core'] as $item) {
                fwrite(STDOUT, '  - ' . $item['path'] . PHP_EOL);
            }
        }

        $result = $manager->applyPlan($plan, $dryRun, $force);
        $touched = $result['touched'];
        $skipped = $result['skipped'];
        $failed = $result['failed'];

        fwrite(STDOUT, PHP_EOL . 'Result Summary:' . PHP_EOL);
        fwrite(STDOUT, '  Touched: ' . count($touched) . PHP_EOL);
        fwrite(STDOUT, '  Skipped: ' . count($skipped) . PHP_EOL);
        fwrite(STDOUT, '  Failed : ' . count($failed) . PHP_EOL);

        if ($touched !== []) {
            fwrite(STDOUT, PHP_EOL . 'Touched Files:' . PHP_EOL);
            foreach ($touched as $path) {
                fwrite(STDOUT, '  - ' . $path . ($dryRun ? ' (would update)' : ' (updated)') . PHP_EOL);
            }
        }

        if ($skipped !== []) {
            fwrite(STDOUT, PHP_EOL . 'Skipped Files:' . PHP_EOL);
            foreach ($skipped as $item) {
                fwrite(STDOUT, '  - ' . $item['path'] . ' (' . $item['reason'] . ')' . PHP_EOL);
            }
        }

        if ($failed !== []) {
            fwrite(STDOUT, PHP_EOL . 'Failed Files:' . PHP_EOL);
            foreach ($failed as $item) {
                fwrite(STDOUT, '  - ' . $item['path'] . ' (' . $item['reason'] . ')' . PHP_EOL);
            }
            return 1;
        }

        if ($dryRun) {
            fwrite(STDOUT, PHP_EOL . '[OK] Dry-run complete. No files were modified.' . PHP_EOL);
            fwrite(STDOUT, 'Use `php aiti upgrade:apply --apply` after review.' . PHP_EOL);
            return 0;
        }

        fwrite(STDOUT, PHP_EOL . '[OK] Upgrade patches applied.' . PHP_EOL);
        fwrite(STDOUT, 'Backups are stored as `*.bak.YmdHis` for touched files.' . PHP_EOL);
        return 0;
    }

    /**
     * @param array<int, string> $args
     */
    private function argumentValue(array $args, string $name): ?string
    {
        foreach ($args as $arg) {
            if (!str_starts_with($arg, '--' . $name . '=')) {
                continue;
            }

            $value = trim(substr($arg, strlen($name) + 3));
            return $value !== '' ? $value : null;
        }

        return null;
    }

    /**
     * @param array<int, string> $args
     */
    private function hasFlag(array $args, string $flag): bool
    {
        return in_array($flag, $args, true);
    }
}

