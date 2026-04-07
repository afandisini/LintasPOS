<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use System\Http\Request;
use System\Cli\Commands\RouteCacheCommand;
use System\Foundation\Config;

class RouterTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = dirname(__DIR__, 2);
        $_ENV = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    protected function tearDown(): void
    {
        $cachedRouteFile = $this->basePath . '/storage/cache/routes.php';
        if (is_file($cachedRouteFile)) {
            @unlink($cachedRouteFile);
        }
    }

    public function testHomeRouteReturns200(): void
    {
        $app = require $this->basePath . '/bootstrap/app.php';
        $response = $app->kernel()->handle(Request::create('GET', '/'));

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('AitiCore Flex', $response->content());
    }

    public function testHeadRequestUsesGetRouteWithoutBody(): void
    {
        $app = require $this->basePath . '/bootstrap/app.php';
        $response = $app->kernel()->handle(Request::create('HEAD', '/'));

        $this->assertSame(200, $response->statusCode());
        $this->assertSame('', $response->content());
    }

    public function testMissingRouteUses404View(): void
    {
        $app = require $this->basePath . '/bootstrap/app.php';
        $response = $app->kernel()->handle(Request::create('GET', '/missing-page'));

        $this->assertSame(404, $response->statusCode());
        $this->assertStringContainsString('404', $response->content());
        $this->assertStringContainsString('Halaman tidak ditemukan', $response->content());
    }

    public function testRouterScriptLetsPublicFilesPassThrough(): void
    {
        $_SERVER['REQUEST_URI'] = '/favicon.ico';

        $result = require $this->basePath . '/router.php';

        $this->assertFalse($result);
    }

    public function testMethodNotAllowedReturns405WithAllowHeader(): void
    {
        $app = require $this->basePath . '/bootstrap/app.php';
        $response = $app->kernel()->handle(Request::create('POST', '/api/ping'));

        $this->assertSame(405, $response->statusCode());
        $this->assertArrayHasKey('Allow', $response->headers());
        $this->assertSame('GET, HEAD', $response->headers()['Allow']);
    }

    public function testRouteHandlerResolvesTypedDependencyFromContainer(): void
    {
        $app = require $this->basePath . '/bootstrap/app.php';
        $app->router()->get('/_di_check', static function (Config $config): string {
            return (string) $config->get('app.name');
        });

        $response = $app->kernel()->handle(Request::create('GET', '/_di_check'));

        $this->assertSame(200, $response->statusCode());
        $this->assertSame('AitiCore Flex', trim($response->content()));
    }

    public function testRouteCacheCommandBuildsArtifactAndRuntimeLoadsIt(): void
    {
        $app = require $this->basePath . '/bootstrap/app.php';
        $command = new RouteCacheCommand();
        $code = $command->handle([], $app);

        $this->assertSame(0, $code);
        $cachedRouteFile = $this->basePath . '/storage/cache/routes.php';
        $this->assertFileExists($cachedRouteFile);

        $cachedApp = require $this->basePath . '/bootstrap/app.php';
        $response = $cachedApp->kernel()->handle(Request::create('GET', '/api/ping'));
        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('"status":"ok"', $response->content());
    }
}
