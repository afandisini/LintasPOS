<?php

declare(strict_types=1);

namespace System\Cli\Commands;

use System\Cli\Command;
use System\Foundation\Application;

class OptimizeCommand extends Command
{
    public function name(): string
    {
        return 'optimize';
    }

    public function description(): string
    {
        return 'Clear framework cache artifacts (config, routes, views)';
    }

    public function handle(array $args, Application $app): int
    {
        $commands = [
            new ConfigClearCommand(),
            new RouteClearCommand(),
            new ViewClearCommand(),
        ];

        $hasError = false;
        foreach ($commands as $command) {
            $code = $command->handle([], $app);
            if ($code !== 0) {
                $hasError = true;
            }
        }

        if ($hasError) {
            fwrite(STDOUT, '[ERR] Optimize completed with errors.' . PHP_EOL);
            return 1;
        }

        fwrite(STDOUT, '[OK] Optimize complete.' . PHP_EOL);
        return 0;
    }
}
