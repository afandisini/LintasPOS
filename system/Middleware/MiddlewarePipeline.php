<?php

declare(strict_types=1);

namespace System\Middleware;

use System\Foundation\Application;

class MiddlewarePipeline
{
    public function __construct(private ?Application $app = null)
    {
    }

    /**
     * @param array<int, mixed> $middlewares
     * @param callable $destination
     */
    public function process(object $request, array $middlewares, callable $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($middlewares),
            function (callable $next, mixed $middleware): callable {
                return function (object $request) use ($middleware, $next): mixed {
                    if (is_callable($middleware)) {
                        return $middleware($request, $next);
                    }

                    if (is_string($middleware) && class_exists($middleware)) {
                        $instance = $this->app?->make($middleware) ?? new $middleware();

                        if (method_exists($instance, 'handle')) {
                            return $instance->handle($request, $next);
                        }
                    }

                    return $next($request);
                };
            },
            $destination(...)
        );

        return $pipeline($request);
    }
}
