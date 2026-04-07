<?php

declare(strict_types=1);

namespace System\Upgrade;

use RuntimeException;
use System\Support\FileSystem;

final class UpgradeManager
{
    /**
     * @var array<int, string>
     */
    private array $userOwnedPrefixes = ['app/', 'routes/', 'database/'];

    public function __construct(private string $basePath)
    {
    }

    /**
     * @return array{
     *   latest: string,
     *   tracks: array<int, array<string, mixed>>
     * }
     */
    public function loadCatalog(): array
    {
        $catalogFile = $this->basePath . DIRECTORY_SEPARATOR . 'upgrade-guides' . DIRECTORY_SEPARATOR . 'index.php';
        if (!is_file($catalogFile)) {
            throw new RuntimeException('Upgrade catalog not found: ' . $catalogFile);
        }

        $catalog = require $catalogFile;
        if (!is_array($catalog)) {
            throw new RuntimeException('Upgrade catalog must return an array.');
        }

        $latest = (string) ($catalog['latest'] ?? '');
        $tracks = $catalog['tracks'] ?? null;
        if ($latest === '' || !is_array($tracks)) {
            throw new RuntimeException('Upgrade catalog must define `latest` and `tracks`.');
        }

        return [
            'latest' => $latest,
            'tracks' => $tracks,
        ];
    }

    public function currentVersion(): string
    {
        $versionFile = $this->basePath . DIRECTORY_SEPARATOR . 'VERSION';
        if (!is_file($versionFile)) {
            throw new RuntimeException('VERSION file not found.');
        }

        $version = trim((string) file_get_contents($versionFile));
        if ($version === '') {
            throw new RuntimeException('VERSION file is empty.');
        }

        return $version;
    }

    /**
     * @param array{
     *   latest: string,
     *   tracks: array<int, array<string, mixed>>
     * } $catalog
     * @return array<int, array<string, mixed>>
     */
    public function buildPlan(array $catalog, string $from, string $to): array
    {
        if ($from === $to) {
            return [];
        }

        $tracks = $catalog['tracks'];
        $current = $from;
        $plan = [];
        $guard = 0;

        while ($current !== $to) {
            $guard++;
            if ($guard > 50) {
                throw new RuntimeException('Upgrade plan exceeded max hop count.');
            }

            $nextStep = $this->findNextStep($tracks, $current, $to);
            if ($nextStep === null) {
                throw new RuntimeException(sprintf('No upgrade path found from %s to %s.', $current, $to));
            }

            $plan[] = $nextStep;
            $current = (string) ($nextStep['to'] ?? '');
        }

        return $plan;
    }

    /**
     * @param array<int, array<string, mixed>> $tracks
     */
    private function findNextStep(array $tracks, string $from, string $target): ?array
    {
        $candidates = [];
        foreach ($tracks as $track) {
            if (!is_array($track)) {
                continue;
            }

            $trackFrom = (string) ($track['from'] ?? '');
            if ($trackFrom !== $from) {
                continue;
            }

            $candidates[] = $track;
        }

        if ($candidates === []) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if ((string) ($candidate['to'] ?? '') === $target) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    /**
     * @param array<int, array<string, mixed>> $plan
     * @return array{
     *   blocked_user_owned: array<int, array<string, string>>,
     *   modified_core: array<int, array<string, string>>
     * }
     */
    public function detectConflicts(array $plan): array
    {
        $blockedUserOwned = [];
        $modifiedCore = [];

        foreach ($plan as $step) {
            $patches = $step['patches'] ?? [];
            if (!is_array($patches)) {
                continue;
            }

            foreach ($patches as $patch) {
                if (!is_array($patch)) {
                    continue;
                }

                $target = $this->normalizeRelativePath((string) ($patch['target'] ?? ''));
                if ($target === '') {
                    continue;
                }

                if ($this->isUserOwnedPath($target)) {
                    $blockedUserOwned[] = [
                        'path' => $target,
                        'reason' => 'User-owned path cannot be overwritten by framework upgrade.',
                    ];
                    continue;
                }

                $expectedChecksum = (string) ($patch['expected_checksum'] ?? '');
                if ($expectedChecksum === '') {
                    continue;
                }

                $absolutePath = $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
                if (!is_file($absolutePath)) {
                    continue;
                }

                $currentChecksum = hash_file('sha256', $absolutePath);
                if (!is_string($currentChecksum) || strtolower($currentChecksum) === strtolower($expectedChecksum)) {
                    continue;
                }

                $modifiedCore[] = [
                    'path' => $target,
                    'reason' => 'Core file differs from expected checksum. Manual review recommended.',
                ];
            }
        }

        return [
            'blocked_user_owned' => $blockedUserOwned,
            'modified_core' => $modifiedCore,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $plan
     * @return array{
     *   touched: array<int, string>,
     *   skipped: array<int, array<string, string>>,
     *   failed: array<int, array<string, string>>
     * }
     */
    public function applyPlan(array $plan, bool $dryRun = true, bool $force = false): array
    {
        $touched = [];
        $skipped = [];
        $failed = [];

        foreach ($plan as $step) {
            $patches = $step['patches'] ?? [];
            if (!is_array($patches)) {
                continue;
            }

            foreach ($patches as $patch) {
                if (!is_array($patch)) {
                    continue;
                }

                $target = $this->normalizeRelativePath((string) ($patch['target'] ?? ''));
                if ($target === '') {
                    continue;
                }

                if ($this->isUserOwnedPath($target)) {
                    $skipped[] = [
                        'path' => $target,
                        'reason' => 'Skipped user-owned path.',
                    ];
                    continue;
                }

                $expectedChecksum = (string) ($patch['expected_checksum'] ?? '');
                $targetAbs = $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);

                if (!$force && $expectedChecksum !== '' && is_file($targetAbs)) {
                    $currentChecksum = hash_file('sha256', $targetAbs);
                    if (is_string($currentChecksum) && strtolower($currentChecksum) !== strtolower($expectedChecksum)) {
                        $skipped[] = [
                            'path' => $target,
                            'reason' => 'Skipped modified core file (checksum mismatch). Use --force to override.',
                        ];
                        continue;
                    }
                }

                $strategy = strtolower((string) ($patch['strategy'] ?? 'replace'));
                if ($strategy === 'marker_merge') {
                    $result = $this->applyMarkerMergePatch($patch, $targetAbs, $dryRun);
                    if ($result['ok']) {
                        $touched[] = $target;
                        continue;
                    }

                    $failed[] = [
                        'path' => $target,
                        'reason' => $result['reason'],
                    ];
                    continue;
                }

                $result = $this->applyReplacePatch($patch, $targetAbs, $dryRun);
                if ($result['ok']) {
                    $touched[] = $target;
                    continue;
                }

                $failed[] = [
                    'path' => $target,
                    'reason' => $result['reason'],
                ];
            }
        }

        return [
            'touched' => array_values(array_unique($touched)),
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    /**
     * @param array<string, mixed> $patch
     * @return array{ok: bool, reason: string}
     */
    private function applyReplacePatch(array $patch, string $targetAbs, bool $dryRun): array
    {
        $template = (string) ($patch['template'] ?? '');
        if ($template === '') {
            return ['ok' => false, 'reason' => 'Missing template path.'];
        }

        $templateAbs = $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $template);
        if (!is_file($templateAbs)) {
            return ['ok' => false, 'reason' => 'Template not found: ' . $template];
        }

        if ($dryRun) {
            return ['ok' => true, 'reason' => 'dry-run'];
        }

        $backup = (bool) ($patch['backup'] ?? true);
        if ($backup && is_file($targetAbs)) {
            $bak = $targetAbs . '.bak.' . date('YmdHis');
            if (!copy($targetAbs, $bak)) {
                return ['ok' => false, 'reason' => 'Failed to write backup: ' . $bak];
            }
        }

        FileSystem::ensureDir(dirname($targetAbs));
        if (!copy($templateAbs, $targetAbs)) {
            return ['ok' => false, 'reason' => 'Failed to write target file.'];
        }

        return ['ok' => true, 'reason' => 'applied'];
    }

    /**
     * @param array<string, mixed> $patch
     * @return array{ok: bool, reason: string}
     */
    private function applyMarkerMergePatch(array $patch, string $targetAbs, bool $dryRun): array
    {
        $markerStart = (string) ($patch['marker_start'] ?? '');
        $markerEnd = (string) ($patch['marker_end'] ?? '');
        $snippet = (string) ($patch['template'] ?? '');

        if ($markerStart === '' || $markerEnd === '' || $snippet === '') {
            return ['ok' => false, 'reason' => 'Missing marker merge configuration.'];
        }

        if (!is_file($targetAbs)) {
            return ['ok' => false, 'reason' => 'Target file not found for marker merge.'];
        }

        $snippetAbs = $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $snippet);
        if (!is_file($snippetAbs)) {
            return ['ok' => false, 'reason' => 'Snippet template not found: ' . $snippet];
        }

        $targetContent = file_get_contents($targetAbs);
        $snippetContent = file_get_contents($snippetAbs);
        if (!is_string($targetContent) || !is_string($snippetContent)) {
            return ['ok' => false, 'reason' => 'Unable to read marker merge source/target.'];
        }

        $pattern = '/' . preg_quote($markerStart, '/') . '.*?' . preg_quote($markerEnd, '/') . '/s';
        if (!preg_match($pattern, $targetContent)) {
            return ['ok' => false, 'reason' => 'Marker pair not found in target file.'];
        }

        $replacement = $markerStart . PHP_EOL . $snippetContent . PHP_EOL . $markerEnd;
        $merged = (string) preg_replace($pattern, $replacement, $targetContent, 1);

        if ($dryRun) {
            return ['ok' => true, 'reason' => 'dry-run'];
        }

        $backup = (bool) ($patch['backup'] ?? true);
        if ($backup && is_file($targetAbs)) {
            $bak = $targetAbs . '.bak.' . date('YmdHis');
            if (!copy($targetAbs, $bak)) {
                return ['ok' => false, 'reason' => 'Failed to write backup: ' . $bak];
            }
        }

        if (file_put_contents($targetAbs, $merged) === false) {
            return ['ok' => false, 'reason' => 'Unable to write merged file.'];
        }

        return ['ok' => true, 'reason' => 'applied'];
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        return ltrim($path, '/');
    }

    private function isUserOwnedPath(string $path): bool
    {
        foreach ($this->userOwnedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

