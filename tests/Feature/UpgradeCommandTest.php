<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class UpgradeCommandTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = dirname(__DIR__, 2);
    }

    public function testUpgradeCheckCommandReportsPlannedStep(): void
    {
        $output = $this->runCli('upgrade:check --from=v0.2.0 --target=v0.3.0');

        $this->assertStringContainsString('Upgrade Check', $output);
        $this->assertStringContainsString('v0.2.0 -> v0.3.0', $output);
        $this->assertStringContainsString('Mode: read-only', $output);
    }

    public function testUpgradeApplyDryRunDoesNotModifyFiles(): void
    {
        $output = $this->runCli('upgrade:apply --from=v0.2.0 --target=v0.3.0');

        $this->assertStringContainsString('Mode  : dry-run', $output);
        $this->assertStringContainsString('[OK] Dry-run complete. No files were modified.', $output);
        $this->assertStringContainsString('Touched: 0', $output);
    }

    private function runCli(string $command): string
    {
        $php = escapeshellarg(PHP_BINARY);
        $aiti = escapeshellarg($this->basePath . DIRECTORY_SEPARATOR . 'aiti');
        $full = $php . ' ' . $aiti . ' ' . $command . ' 2>&1';

        $cwd = getcwd();
        chdir($this->basePath);
        $output = shell_exec($full);
        if ($cwd !== false) {
            chdir($cwd);
        }

        return is_string($output) ? $output : '';
    }
}
