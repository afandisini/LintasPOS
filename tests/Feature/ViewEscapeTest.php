<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use System\View\View;

class ViewEscapeTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixturePath = dirname(__DIR__, 2) . '/app/Views/_escape_test.php';
        file_put_contents($this->fixturePath, '<?= $payload ?>');
    }

    protected function tearDown(): void
    {
        if (is_file($this->fixturePath)) {
            unlink($this->fixturePath);
        }
    }

    public function testEscapesTemplateDataByDefault(): void
    {
        $view = new View(dirname(__DIR__, 2) . '/app/Views');
        $html = $view->render('_escape_test', ['payload' => '<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }
}
