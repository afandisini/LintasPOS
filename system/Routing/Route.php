<?php

declare(strict_types=1);

namespace System\Routing;

class Route
{
    /**
     * @param callable|array{0: class-string, 1: string} $action
     * @param array<int, class-string> $middleware
     */
    public function __construct(
        private string $method,
        private string $uri,
        private mixed $action,
        private ?string $group = null,
        private array $middleware = []
    ) {
        $this->method = strtoupper($this->method);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function action(): mixed
    {
        return $this->action;
    }

    public function group(): ?string
    {
        return $this->group;
    }

    /**
     * @return array<int, class-string>
     */
    public function middleware(): array
    {
        return $this->middleware;
    }

    /**
     * @param class-string|array<int, class-string> $middleware
     */
    public function withMiddleware(string|array $middleware): self
    {
        $items = is_array($middleware) ? $middleware : [$middleware];
        foreach ($items as $item) {
            if (!is_string($item) || $item === '') {
                continue;
            }
            $this->middleware[] = $item;
        }

        $this->middleware = array_values(array_unique($this->middleware));
        return $this;
    }

    /**
     * @return array<string, string>|null
     */
    public function match(string $method, string $path): ?array
    {
        if ($this->method !== strtoupper($method)) {
            return null;
        }

        $pattern = $this->compilePattern();

        if (!is_string($pattern) || preg_match($pattern, $path, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = (string) $value;
            }
        }

        return $params;
    }

    public function matchesPath(string $path): bool
    {
        $pattern = $this->compilePattern();
        return is_string($pattern) && preg_match($pattern, $path) === 1;
    }

    private function compilePattern(): ?string
    {
        $pattern = preg_replace('#\{([^/]+)\}#', '(?P<$1>[^/]+)', $this->uri);
        if (!is_string($pattern)) {
            return null;
        }

        return '#^' . $pattern . '$#';
    }
}
