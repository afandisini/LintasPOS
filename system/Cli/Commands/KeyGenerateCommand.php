<?php

declare(strict_types=1);

namespace System\Cli\Commands;

use System\Cli\Command;
use System\Foundation\Application;

class KeyGenerateCommand extends Command
{
    public function name(): string
    {
        return 'key:generate';
    }

    public function description(): string
    {
        return 'Generate APP_KEY and write it to .env';
    }

    public function handle(array $args, Application $app): int
    {
        $envPath = $app->basePath('.env');
        if (!is_file($envPath)) {
            $example = $app->basePath('.env.example');
            if (is_file($example)) {
                copy($example, $envPath);
            } else {
                file_put_contents($envPath, "APP_KEY=\n");
            }
        }

        $key = 'base64:' . base64_encode(random_bytes(32));
        $content = (string) file_get_contents($envPath);

        if (preg_match('/^APP_KEY=.*$/m', $content) === 1) {
            $content = (string) preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $content);
        } else {
            $content .= PHP_EOL . 'APP_KEY=' . $key . PHP_EOL;
        }

        file_put_contents($envPath, $content);
        fwrite(STDOUT, 'Application key set successfully.' . PHP_EOL);
        return 0;
    }
}
