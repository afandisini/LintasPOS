<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\Api\SatuanController;
use App\Services\Database;
use App\Services\SatuanApiService;
use PDO;
use PHPUnit\Framework\TestCase;
use System\Http\Request;

final class ApiV1Test extends TestCase
{
    private string $dbFile;
    private array $originalEnv;
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV;
        $this->originalServer = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        $_ENV = [];
        $this->dbFile = '';
        $this->resetDatabaseConnection();
    }

    private function prepareSqliteDatabase(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('SQLite PDO driver is not installed.');
        }

        $this->dbFile = sys_get_temp_dir() . '/lintaspos_api_' . bin2hex(random_bytes(4)) . '.sqlite';
        $_ENV['DB_DSN'] = 'sqlite:' . $this->dbFile;
        $_ENV['DB_USERNAME'] = '';
        $_ENV['DB_PASSWORD'] = '';
        $this->resetDatabaseConnection();
        $pdo = new PDO($_ENV['DB_DSN']);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, user TEXT, pass TEXT, hak_akses_id INTEGER, active TEXT, avatar INTEGER)');
        $pdo->exec('CREATE TABLE hak_akses (id INTEGER PRIMARY KEY, hak_akses TEXT)');
        $pdo->exec('CREATE TABLE api_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, name TEXT, token_hash TEXT UNIQUE, expires_at TEXT, last_used_at TEXT, revoked_at TEXT, created_at TEXT)');
        $pdo->exec("INSERT INTO hak_akses (id, hak_akses) VALUES (1, 'Administrator')");
        $password = password_hash('secret', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, user, pass, hak_akses_id, active) VALUES ('Afan', 'afan@example.test', 'afan', :pass, 1, '1')");
        $stmt->execute(['pass' => $password]);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseConnection();
        if ($this->dbFile !== '') {
            @unlink($this->dbFile);
        }
        $_ENV = $this->originalEnv;
        $_SERVER = $this->originalServer;
    }

    public function testHealthUsesV1Envelope(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $response = $app->kernel()->handle(Request::create('GET', '/api_v1/health'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('application/json', $response->headers()['Content-Type']);
        self::assertArrayHasKey('X-Request-Id', $response->headers());
        self::assertSame('1', $response->headers()['X-API-Version']);
        self::assertStringContainsString('"success":true', $response->content());
        self::assertStringContainsString('"request_id"', $response->content());
    }

    public function testLoginMeAndLogoutUseHashedBearerToken(): void
    {
        $this->prepareSqliteDatabase();
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        // bootstrap loads the repository .env; restore the isolated test database after boot.
        $_ENV['DB_DSN'] = 'sqlite:' . $this->dbFile;
        $_ENV['DB_USERNAME'] = '';
        $_ENV['DB_PASSWORD'] = '';
        $this->resetDatabaseConnection();
        $login = $app->kernel()->handle(Request::create('POST', '/api_v1/auth/login', [
            'identity' => 'afan', 'password' => 'secret', 'device_name' => 'test',
        ]));
        self::assertSame(200, $login->statusCode());
        $body = json_decode($login->content(), true);
        $token = (string) ($body['data']['token'] ?? '');
        self::assertNotSame('', $token);
        self::assertStringNotContainsString($token, (string) (new PDO($_ENV['DB_DSN']))->query('SELECT token_hash FROM api_tokens')->fetchColumn());

        $me = $app->kernel()->handle(Request::create('GET', '/api_v1/auth/me', [], ['Authorization' => 'Bearer ' . $token]));
        self::assertSame(200, $me->statusCode());
        self::assertStringContainsString('afan', $me->content());

        $logout = $app->kernel()->handle(Request::create('POST', '/api_v1/auth/logout', [], ['Authorization' => 'Bearer ' . $token]));
        self::assertSame(200, $logout->statusCode());
        $after = $app->kernel()->handle(Request::create('GET', '/api_v1/auth/me', [], ['Authorization' => 'Bearer ' . $token]));
        self::assertSame(401, $after->statusCode());
    }

    public function testProtectedEndpointRequiresBearerToken(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $response = $app->kernel()->handle(Request::create('GET', '/api_v1/auth/me'));

        self::assertSame(401, $response->statusCode());
        self::assertStringContainsString('UNAUTHENTICATED', $response->content());
    }

    public function testKategoriCrudRequiresBearerToken(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $response = $app->kernel()->handle(Request::create('GET', '/api_v1/kategori?page=1&per_page=20'));

        self::assertSame(401, $response->statusCode());
        self::assertStringContainsString('UNAUTHENTICATED', $response->content());
    }

    public function testSatuanCrudRequiresBearerToken(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $response = $app->kernel()->handle(Request::create('GET', '/api_v1/satuan'));

        self::assertSame(401, $response->statusCode());
        self::assertStringContainsString('UNAUTHENTICATED', $response->content());
    }

    /** @dataProvider masterDataPaths */
    public function testAdditionalMasterDataRequiresBearerToken(string $path): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $response = $app->kernel()->handle(Request::create('GET', $path));
        self::assertSame(401, $response->statusCode());
    }

    public static function masterDataPaths(): array
    {
        return [['/api_v1/barang'], ['/api_v1/jasa'], ['/api_v1/pelanggan'], ['/api_v1/supplier'], ['/api_v1/diskon']];
    }

    /** @dataProvider auxiliaryApiPaths */
    public function testLookupAndMediaRequireBearerToken(string $path): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $response = $app->kernel()->handle(Request::create('GET', $path));
        self::assertSame(401, $response->statusCode());
    }

    public function testSatuanValidationRejectsMissingName(): void
    {
        $response = (new SatuanController())->store(Request::create('POST', '/api_v1/satuan', ['nama' => '']), new SatuanApiService());

        self::assertSame(422, $response->statusCode());
        self::assertStringContainsString('nama', $response->content());
    }

    public static function auxiliaryApiPaths(): array
    {
        return [['/api_v1/lookups/barang'], ['/api_v1/lookups/jasa'], ['/api_v1/lookups/pelanggan'], ['/api_v1/lookups/supplier'], ['/api_v1/media/1']];
    }

    private function resetDatabaseConnection(): void
    {
        $reflection = new \ReflectionClass(Database::class);
        $property = $reflection->getProperty('connection');
        $property->setValue(null);
    }
}
