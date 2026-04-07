<?php

declare(strict_types=1);

namespace System\Cli\Commands;

use System\Cli\Command;
use System\Cli\Console;
use System\Foundation\Application;

class ListCommand extends Command
{
    public function __construct(private Console $console)
    {
    }

    public function name(): string
    {
        return 'list';
    }

    public function description(): string
    {
        return 'List all available commands';
    }

    public function handle(array $args, Application $app): int
    {
        fwrite(STDOUT, brand_name() . ' CLI ' . Console::version() . PHP_EOL);
        fwrite(STDOUT, 'Available commands:' . PHP_EOL);

        foreach ($this->console->commands() as $name => $command) {
            fwrite(STDOUT, sprintf("  %-20s %s\n", $name, $command->description()));
        }

        return 0;
    }
}
