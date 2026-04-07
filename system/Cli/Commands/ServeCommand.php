<?php

declare(strict_types=1);

namespace System\Cli\Commands;

use System\Cli\Command;
use System\Foundation\Application;

class ServeCommand extends Command
{
    public function name(): string
    {
        return 'serve';
    }

    public function description(): string
    {
        return 'Serve the application using PHP built-in server';
    }

    public function aliases(): array
    {
        return ['server'];
    }

    public function handle(array $args, Application $app): int
    {
        $host = '127.0.0.1';
        $port = '8000';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--host=')) {
                $host = substr($arg, 7);
            }

            if (str_starts_with($arg, '--port=')) {
                $port = substr($arg, 7);
            }
        }

        $router = $app->basePath('router.php');
        $command = sprintf('php -S %s:%s %s', $host, $port, escapeshellarg($router));
        fwrite(STDOUT, 'Starting server on http://' . $host . ':' . $port . PHP_EOL);
        passthru($command, $code);
        return $code;
    }
}
