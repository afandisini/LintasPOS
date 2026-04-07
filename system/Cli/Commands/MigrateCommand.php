<?php

declare(strict_types=1);

namespace System\Cli\Commands;

use PDO;
use PDOException;
use System\Cli\Command;
use System\Foundation\Application;

class MigrateCommand extends Command
{
    private const MIGRATIONS_TABLE = 'aiti_migrations';

    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return 'Manage SQL migrations (update, drop, status, rollback)';
    }

    public function aliases(): array
    {
        return ['Migrate', 'migrate:update', 'migrate:drop', 'migrate:status', 'migrate:rollback'];
    }

    public function handle(array $args, Application $app): int
    {
        $action = $this->resolveAction($args);
        if ($action === null) {
            fwrite(STDOUT, "Usage: php aiti migrate [update|drop|status|rollback] [--step=N]\n");
            return 1;
        }

        try {
            $pdo = $this->connect();
            $this->ensureMigrationsTable($pdo);
        } catch (\Throwable $exception) {
            fwrite(STDOUT, 'Migration setup failed: ' . $exception->getMessage() . PHP_EOL);
            return 1;
        }

        return match ($action) {
            'update' => $this->runUpdate($app, $pdo),
            'drop' => $this->runDrop($app, $pdo),
            'status' => $this->runStatus($app, $pdo),
            'rollback' => $this->runRollback($app, $pdo, $this->resolveStep($args)),
            default => 1,
        };
    }

    private function runUpdate(Application $app, PDO $pdo): int
    {
        $directory = $app->basePath('database/update');
        if (!is_dir($directory)) {
            fwrite(STDOUT, 'Migration directory not found: ' . $directory . PHP_EOL);
            return 1;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . '*.sql');
        if ($files === false || $files === []) {
            fwrite(STDOUT, 'No SQL files found in ' . $directory . PHP_EOL);
            return 0;
        }
        sort($files);

        $applied = $this->appliedMigrations($pdo);
        $pending = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!isset($applied[$name])) {
                $pending[] = $file;
            }
        }

        if ($pending === []) {
            fwrite(STDOUT, 'Nothing to migrate. Database is up to date.' . PHP_EOL);
            return 0;
        }

        $batch = $this->nextBatchNumber($pdo);

        $useTransaction = $this->shouldUseTransaction($pdo);

        try {
            if ($useTransaction) {
                $pdo->beginTransaction();
            }
            foreach ($pending as $file) {
                $name = basename($file);
                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new \RuntimeException('Unable to read migration file: ' . $file);
                }

                fwrite(STDOUT, 'Running ' . $name . PHP_EOL);
                $pdo->exec($sql);
                $this->recordMigration($pdo, $name, $batch);
            }
            if ($useTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($useTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            fwrite(STDOUT, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
            return 1;
        }

        fwrite(STDOUT, 'Migration update completed. Batch ' . $batch . '.' . PHP_EOL);
        return 0;
    }

    private function runDrop(Application $app, PDO $pdo): int
    {
        $directory = $app->basePath('database/drop');
        if (!is_dir($directory)) {
            fwrite(STDOUT, 'Migration directory not found: ' . $directory . PHP_EOL);
            return 1;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . '*.sql');
        if ($files === false || $files === []) {
            fwrite(STDOUT, 'No SQL files found in ' . $directory . PHP_EOL);
            return 0;
        }
        sort($files);

        $useTransaction = $this->shouldUseTransaction($pdo);

        try {
            if ($useTransaction) {
                $pdo->beginTransaction();
            }
            foreach ($files as $file) {
                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new \RuntimeException('Unable to read migration file: ' . $file);
                }

                fwrite(STDOUT, 'Running ' . basename($file) . PHP_EOL);
                $pdo->exec($sql);
            }

            $pdo->exec('DELETE FROM ' . self::MIGRATIONS_TABLE);
            if ($useTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($useTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            fwrite(STDOUT, 'Migration drop failed: ' . $exception->getMessage() . PHP_EOL);
            return 1;
        }

        fwrite(STDOUT, 'Migration drop completed and migration history cleared.' . PHP_EOL);
        return 0;
    }

    private function runStatus(Application $app, PDO $pdo): int
    {
        $directory = $app->basePath('database/update');
        if (!is_dir($directory)) {
            fwrite(STDOUT, 'Migration directory not found: ' . $directory . PHP_EOL);
            return 1;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . '*.sql');
        if ($files === false || $files === []) {
            fwrite(STDOUT, 'No SQL files found in ' . $directory . PHP_EOL);
            return 0;
        }
        sort($files);

        $applied = $this->appliedMigrations($pdo);
        fwrite(STDOUT, sprintf("%-8s %-7s %-20s %s\n", 'STATUS', 'BATCH', 'EXECUTED_AT', 'MIGRATION'));
        foreach ($files as $file) {
            $name = basename($file);
            $row = $applied[$name] ?? null;
            $status = $row === null ? 'Pending' : 'Ran';
            $batch = $row['batch'] ?? '-';
            $executedAt = $row['executed_at'] ?? '-';
            fwrite(STDOUT, sprintf("%-8s %-7s %-20s %s\n", $status, (string) $batch, (string) $executedAt, $name));
        }

        return 0;
    }

    private function runRollback(Application $app, PDO $pdo, int $step): int
    {
        if ($step < 1) {
            fwrite(STDOUT, 'Rollback step must be >= 1.' . PHP_EOL);
            return 1;
        }

        $batches = $this->latestBatches($pdo, $step);
        if ($batches === []) {
            fwrite(STDOUT, 'Nothing to rollback.' . PHP_EOL);
            return 0;
        }

        $migrations = $this->migrationsInBatches($pdo, $batches);
        if ($migrations === []) {
            fwrite(STDOUT, 'Nothing to rollback.' . PHP_EOL);
            return 0;
        }

        $useTransaction = $this->shouldUseTransaction($pdo);

        try {
            if ($useTransaction) {
                $pdo->beginTransaction();
            }
            foreach ($migrations as $migration) {
                $name = (string) $migration['migration'];
                $dropFile = $app->basePath('database/drop/' . $name);
                if (!is_file($dropFile)) {
                    throw new \RuntimeException('Rollback SQL file not found: ' . $dropFile);
                }

                $sql = file_get_contents($dropFile);
                if ($sql === false) {
                    throw new \RuntimeException('Unable to read rollback file: ' . $dropFile);
                }

                fwrite(STDOUT, 'Rolling back ' . $name . PHP_EOL);
                $pdo->exec($sql);

                $delete = $pdo->prepare('DELETE FROM ' . self::MIGRATIONS_TABLE . ' WHERE migration = :migration');
                $delete->execute(['migration' => $name]);
            }
            if ($useTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($useTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            fwrite(STDOUT, 'Rollback failed: ' . $exception->getMessage() . PHP_EOL);
            return 1;
        }

        fwrite(STDOUT, 'Rollback completed. Batches: ' . implode(', ', array_map('strval', $batches)) . PHP_EOL);
        return 0;
    }

    /**
     * @param array<int, string> $args
     */
    private function resolveAction(array $args): ?string
    {
        $first = $args[0] ?? null;
        if (in_array($first, ['update', 'drop', 'status', 'rollback'], true)) {
            return $first;
        }

        $invoked = $_SERVER['argv'][1] ?? null;
        return match ($invoked) {
            'migrate:update' => 'update',
            'migrate:drop' => 'drop',
            'migrate:status' => 'status',
            'migrate:rollback' => 'rollback',
            default => null,
        };
    }

    /**
     * @param array<int, string> $args
     */
    private function resolveStep(array $args): int
    {
        foreach ($args as $arg) {
            if (!str_starts_with($arg, '--step=')) {
                continue;
            }

            $value = (int) substr($arg, 7);
            if ($value > 0) {
                return $value;
            }
        }

        return 1;
    }

    private function connect(): PDO
    {
        $dsn = trim((string) ($_ENV['DB_DSN'] ?? ''));
        $username = (string) ($_ENV['DB_USERNAME'] ?? '');
        $password = (string) ($_ENV['DB_PASSWORD'] ?? '');

        if ($dsn === '') {
            throw new PDOException('DB_DSN is not configured.');
        }

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function shouldUseTransaction(PDO $pdo): bool
    {
        $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        // MySQL/MariaDB auto-commit many DDL statements, which can break manual COMMIT/ROLLBACK flow.
        return in_array($driver, ['sqlite', 'pgsql'], true);
    }

    private function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::MIGRATIONS_TABLE . ' (' .
            'migration VARCHAR(255) PRIMARY KEY, ' .
            'batch INTEGER NOT NULL, ' .
            'executed_at VARCHAR(25) NOT NULL' .
            ')'
        );
    }

    /**
     * @return array<string, array{migration: string, batch: int, executed_at: string}>
     */
    private function appliedMigrations(PDO $pdo): array
    {
        $query = $pdo->query(
            'SELECT migration, batch, executed_at FROM ' . self::MIGRATIONS_TABLE . ' ORDER BY migration ASC'
        );
        $rows = $query === false ? [] : $query->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $mapped = [];
        foreach ($rows as $row) {
            $name = (string) ($row['migration'] ?? '');
            if ($name === '') {
                continue;
            }

            $mapped[$name] = [
                'migration' => $name,
                'batch' => (int) ($row['batch'] ?? 0),
                'executed_at' => (string) ($row['executed_at'] ?? ''),
            ];
        }

        return $mapped;
    }

    private function nextBatchNumber(PDO $pdo): int
    {
        $query = $pdo->query('SELECT MAX(batch) AS max_batch FROM ' . self::MIGRATIONS_TABLE);
        $row = $query === false ? null : $query->fetch();
        $current = is_array($row) ? (int) ($row['max_batch'] ?? 0) : 0;
        return $current + 1;
    }

    private function recordMigration(PDO $pdo, string $name, int $batch): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO ' . self::MIGRATIONS_TABLE . ' (migration, batch, executed_at) ' .
            'VALUES (:migration, :batch, :executed_at)'
        );
        $statement->execute([
            'migration' => $name,
            'batch' => $batch,
            'executed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function latestBatches(PDO $pdo, int $step): array
    {
        $statement = $pdo->prepare(
            'SELECT DISTINCT batch FROM ' . self::MIGRATIONS_TABLE . ' ORDER BY batch DESC LIMIT :limit'
        );
        $statement->bindValue(':limit', $step, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $batches = [];
        foreach ($rows as $row) {
            $batches[] = (int) ($row['batch'] ?? 0);
        }

        return array_values(array_filter($batches, static fn (int $value): bool => $value > 0));
    }

    /**
     * @param array<int, int> $batches
     * @return array<int, array{migration: string, batch: int}>
     */
    private function migrationsInBatches(PDO $pdo, array $batches): array
    {
        if ($batches === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($batches), '?'));
        $query = $pdo->prepare(
            'SELECT migration, batch FROM ' . self::MIGRATIONS_TABLE .
            ' WHERE batch IN (' . $placeholders . ')' .
            ' ORDER BY batch DESC, migration DESC'
        );
        $query->execute($batches);
        $rows = $query->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = [
                'migration' => (string) ($row['migration'] ?? ''),
                'batch' => (int) ($row['batch'] ?? 0),
            ];
        }

        return $mapped;
    }
}

