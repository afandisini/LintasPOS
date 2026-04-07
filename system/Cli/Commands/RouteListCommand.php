<?php

declare(strict_types=1);

namespace System\Cli\Commands;

use System\Cli\Command;
use System\Foundation\Application;
use System\Routing\Route;

class RouteListCommand extends Command
{
    public function name(): string
    {
        return 'route:list';
    }

    public function description(): string
    {
        return 'Display registered routes';
    }

    public function handle(array $args, Application $app): int
    {
        fwrite(STDOUT, sprintf("%-8s %-24s %-30s %s\n", 'METHOD', 'URI', 'HANDLER', 'GROUP'));
        foreach ($app->router()->routes() as $route) {
            $handler = $this->formatHandler($route);
            fwrite(STDOUT, sprintf(
                "%-8s %-24s %-30s %s\n",
                $route->method(),
                $route->uri(),
                $handler,
                $route->group() ?? '-'
            ));
        }

        return 0;
    }

    private function formatHandler(Route $route): string
    {
        $action = $route->action();
        if (is_array($action)) {
            return $action[0] . '@' . $action[1];
        }

        return 'Closure';
    }
}
