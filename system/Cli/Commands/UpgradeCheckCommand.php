<?php

declare(strict_types=1);

namespace System\Cli\Commands;

use RuntimeException;
use System\Cli\Command;
use System\Foundation\Application;
use System\Upgrade\UpgradeManager;

class UpgradeCheckCommand extends Command
{
    public function name(): string
    {
        return 'upgrade:check';
    }

    public function description(): string
    {
        return 'Inspect upgrade path, risk, and file conflicts (read-only)';
    }

    public function handle(array $args, Application $app): int
    {
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

        fwrite(STDOUT, 'Upgrade Check' . PHP_EOL);
        fwrite(STDOUT, '  From  : ' . $from . PHP_EOL);
        fwrite(STDOUT, '  Target: ' . $target . PHP_EOL);

        if ($plan === []) {
            fwrite(STDOUT, '[OK] No upgrade step required.' . PHP_EOL);
            return 0;
        }

        fwrite(STDOUT, PHP_EOL . 'Planned Steps:' . PHP_EOL);
        foreach ($plan as $step) {
            $guide = (string) ($step['guide'] ?? '-');
            fwrite(STDOUT, '  - ' . (string) $step['from'] . ' -> ' . (string) $step['to'] . ' (guide: ' . $guide . ')' . PHP_EOL);
        }

        $this->printItems('Breaking Changes', $this->flattenList($plan, 'breaking'));
        $this->printItems('Risk Notes', $this->flattenList($plan, 'risks'));
        $this->printItems('Deprecations', $this->flattenList($plan, 'deprecations'));

        $conflicts = $manager->detectConflicts($plan);
        $blocked = $conflicts['blocked_user_owned'];
        $modified = $conflicts['modified_core'];

        fwrite(STDOUT, PHP_EOL . 'Conflict Scan:' . PHP_EOL);
        if ($blocked === [] && $modified === []) {
            fwrite(STDOUT, '  [OK] No conflicts detected against planned patch set.' . PHP_EOL);
        } else {
            foreach ($blocked as $item) {
                fwrite(STDOUT, '  [BLOCKED] ' . $item['path'] . ' - ' . $item['reason'] . PHP_EOL);
            }
            foreach ($modified as $item) {
                fwrite(STDOUT, '  [WARN]    ' . $item['path'] . ' - ' . $item['reason'] . PHP_EOL);
            }
        }

        $patchCount = 0;
        foreach ($plan as $step) {
            $patches = $step['patches'] ?? [];
            if (is_array($patches)) {
                $patchCount += count($patches);
            }
        }

        fwrite(STDOUT, PHP_EOL . 'Patch Entries: ' . $patchCount . PHP_EOL);
        fwrite(STDOUT, 'Mode: read-only (no file was changed).' . PHP_EOL);

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
     * @param array<int, array<string, mixed>> $plan
     * @return array<int, string>
     */
    private function flattenList(array $plan, string $field): array
    {
        $items = [];
        foreach ($plan as $step) {
            $values = $step[$field] ?? [];
            if (!is_array($values)) {
                continue;
            }

            foreach ($values as $value) {
                if (is_string($value) && $value !== '') {
                    $items[] = $value;
                }
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @param array<int, string> $items
     */
    private function printItems(string $title, array $items): void
    {
        fwrite(STDOUT, PHP_EOL . $title . ':' . PHP_EOL);
        if ($items === []) {
            fwrite(STDOUT, '  - none' . PHP_EOL);
            return;
        }

        foreach ($items as $item) {
            fwrite(STDOUT, '  - ' . $item . PHP_EOL);
        }
    }
}

