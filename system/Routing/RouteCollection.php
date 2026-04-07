<?php

declare(strict_types=1);

namespace System\Routing;

class RouteCollection
{
    /**
     * @var array<int, Route>
     */
    private array $routes = [];

    public function add(Route $route): void
    {
        $this->routes[] = $route;
    }

    /**
     * @return array<int, Route>
     */
    public function all(): array
    {
        return $this->routes;
    }
}
