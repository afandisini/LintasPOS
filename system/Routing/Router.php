<?php

declare(strict_types=1);

namespace System\Routing;

use System\Http\Request;

class Router
{
    private RouteCollection $routes;
    private ?string $currentGroup = null;

    /**
     * @var array<string, string>
     */
    private array $currentParameters = [];

    public function __construct()
    {
        $this->routes = new RouteCollection();
    }

    public function setCurrentGroup(?string $group): void
    {
        $this->currentGroup = $group;
    }

    /**
     * @param callable|array{0: class-string, 1: string} $action
     */
    public function get(string $uri, mixed $action): Route
    {
        return $this->add('GET', $uri, $action);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $action
     */
    public function post(string $uri, mixed $action): Route
    {
        return $this->add('POST', $uri, $action);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $action
     */
    public function add(string $method, string $uri, mixed $action): Route
    {
        $route = new Route($method, $uri, $action, $this->currentGroup);
        $this->routes->add($route);
        return $route;
    }

    public function match(Request $request): ?Route
    {
        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();

        foreach ($this->routes->all() as $route) {
            $params = $route->match($method, $request->path());
            if ($params !== null) {
                $this->currentParameters = $params;
                return $route;
            }
        }

        $this->currentParameters = [];
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function currentParameters(): array
    {
        return $this->currentParameters;
    }

    /**
     * @return array<int, Route>
     */
    public function routes(): array
    {
        return $this->routes->all();
    }

    /**
     * @return array<int, string>
     */
    public function allowedMethods(string $path): array
    {
        $allowed = [];
        foreach ($this->routes->all() as $route) {
            if (!$route->matchesPath($path)) {
                continue;
            }

            $allowed[] = $route->method();
        }

        $allowed = array_values(array_unique($allowed));
        if (in_array('GET', $allowed, true) && !in_array('HEAD', $allowed, true)) {
            $allowed[] = 'HEAD';
        }

        sort($allowed);
        return $allowed;
    }

    /**
     * @return array{routes: array<int, array<string, mixed>>, uncacheable: array<int, string>}
     */
    public function exportCacheableRoutes(): array
    {
        $items = [];
        $uncacheable = [];

        foreach ($this->routes->all() as $route) {
            $action = $route->action();
            if (!is_array($action) || count($action) !== 2 || !is_string($action[0]) || !is_string($action[1])) {
                $uncacheable[] = $route->method() . ' ' . $route->uri();
                continue;
            }

            $items[] = [
                'method' => $route->method(),
                'uri' => $route->uri(),
                'group' => $route->group(),
                'middleware' => $route->middleware(),
                'action' => [$action[0], $action[1]],
            ];
        }

        return [
            'routes' => $items,
            'uncacheable' => $uncacheable,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $definitions
     */
    public function importCachedRoutes(array $definitions): void
    {
        $this->routes = new RouteCollection();
        foreach ($definitions as $definition) {
            $method = (string) ($definition['method'] ?? 'GET');
            $uri = (string) ($definition['uri'] ?? '/');
            $group = isset($definition['group']) && is_string($definition['group'])
                ? $definition['group']
                : null;
            $middleware = isset($definition['middleware']) && is_array($definition['middleware'])
                ? $definition['middleware']
                : [];
            $action = $definition['action'] ?? null;
            if (!is_array($action) || count($action) !== 2 || !is_string($action[0]) || !is_string($action[1])) {
                continue;
            }

            $this->routes->add(new Route($method, $uri, $action, $group, $middleware));
        }
    }
}
