<?php

declare(strict_types=1);

namespace Tests\Feature;

use PDO;
use PHPUnit\Framework\TestCase;
use System\Cli\Commands\MigrateCommand;

class MigrateCommandTest extends TestCase
{
    private string $basePath;
    private string $dbFile;
    private string $migrationFileName;
    private string $updatePath;
    private string $dropPath;

    protected function setUp(): void
    {
        $this->basePath = dirname(__DIR__, 2);
        $this->dbFile = $this->basePath . '/storage/test_migrate_command.sqlite';
        $this->migrationFileName = '999999_000000_test_feature_migration.sql';
        $this->updatePath = $this->basePath . '/database/update/' . $this->migrationFileName;
        $this->dropPath = $this->basePath . '/database/drop/' . $this->migrationFileName;

        @unlink($this->dbFile);
        @unlink($this->updatePath);
        @unlink($this->dropPath);

        $_ENV = [];
        $_ENV['DB_DSN'] = 'sqlite:' . str_replace('\\', '/', $this->dbFile);
        $_ENV['DB_USERNAME'] = '';
        $_ENV['DB_PASSWORD'] = '';
    }

    protected function tearDown(): void
    {
        @unlink($this->updatePath);
        @unlink($this->dropPath);
        @unlink($this->dbFile);
    }

    public function testUpdateStatusAndRollbackWorkflow(): void
    {
        file_put_contents($this->updatePath, "CREATE TABLE test_items (id INTEGER PRIMARY KEY, name TEXT);\n");
        file_put_contents($this->dropPath, "DROP TABLE IF EXISTS test_items;\n");

        $app = require $this->basePath . '/bootstrap/app.php';
        $command = new MigrateCommand();

        $this->assertSame(0, $command->handle(['update'], $app));
        $this->assertTrue($this->tableExists('test_items'));
        $this->assertTrue($this->migrationExists($this->migrationFileName));

        $this->assertSame(0, $command->handle(['status'], $app));

        $this->assertSame(0, $command->handle(['rollback', '--step=1'], $app));
        $this->assertFalse($this->tableExists('test_items'));
        $this->assertFalse($this->migrationExists($this->migrationFileName));
    }

    private function connection(): PDO
    {
        return new PDO($_ENV['DB_DSN']);
    }

    private function tableExists(string $table): bool
    {
        $pdo = $this->connection();
        $statement = $pdo->prepare(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table"
        );
        $statement->execute(['table' => $table]);
        return $statement->fetchColumn() !== false;
    }

    private function migrationExists(string $migration): bool
    {
        $pdo = $this->connection();
        $statement = $pdo->prepare(
            "SELECT migration FROM aiti_migrations WHERE migration = :migration"
        );
        $statement->execute(['migration' => $migration]);
        return $statement->fetchColumn() !== false;
    }
}

