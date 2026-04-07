<?php

declare(strict_types=1);

namespace System\Cli;

use System\Foundation\Application;

abstract class Command
{
    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * @return array<int, string>
     */
    public function aliases(): array
    {
        return [];
    }

    /**
     * @param array<int, string> $args
     */
    abstract public function handle(array $args, Application $app): int;
}
