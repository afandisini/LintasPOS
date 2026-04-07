<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use System\Http\Request;

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV = [];
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public function testTokenGeneratedOnWebGetRequest(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->kernel()->handle(Request::create('GET', '/'));

        $this->assertArrayHasKey('_csrf_token', $_SESSION);
        $this->assertIsString($_SESSION['_csrf_token']);
        $this->assertNotEmpty($_SESSION['_csrf_token']);
    }

    public function testPostWithoutTokenBlocked(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $response = $app->kernel()->handle(Request::create('POST', '/contact', ['name' => 'Anon']));

        $this->assertSame(403, $response->statusCode());
    }

    public function testPostWithTokenPasses(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->kernel()->handle(Request::create('GET', '/'));
        $token = $_SESSION['_csrf_token'] ?? '';

        $response = $app->kernel()->handle(Request::create('POST', '/contact', [
            '_token' => $token,
            'name' => 'Anon',
        ]));

        $this->assertSame(302, $response->statusCode());
        $this->assertIsArray($_SESSION['_flash'] ?? null);
        $this->assertSame('success', $_SESSION['_flash']['type'] ?? null);
    }
}
