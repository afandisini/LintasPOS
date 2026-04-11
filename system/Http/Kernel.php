<?php

declare(strict_types=1);

namespace System\Http;

use System\Foundation\Application;
use System\Middleware\MiddlewarePipeline;
use System\Routing\Route;

class Kernel
{
    /**
     * @var array<int, class-string>
     */
    private array $globalMiddleware = [];

    public function __construct(private Application $app)
    {
    }

    public function handle(Request $request): Response
    {
        $headOnly = $request->method() === 'HEAD';
        $route = $this->app->router()->match($request);

        if ($route === null) {
            $allowed = $this->app->router()->allowedMethods($request->path());
            $response = $allowed !== []
                ? $this->methodNotAllowedResponse($allowed, $request)
                : $this->notFoundResponse($request);
            return $headOnly ? $response->withoutBody() : $response;
        }

        $middlewares = array_merge(
            $this->globalMiddleware,
            $route->group() !== null ? $this->app->middlewareGroup($route->group()) : [],
            $route->middleware()
        );

        $pipeline = new MiddlewarePipeline($this->app);
        $result = $pipeline->process(
            $request,
            $middlewares,
            function (Request $request) use ($route): mixed {
                return $this->dispatchToRoute($request, $route);
            }
        );

        $response = $this->normalizeResponse($result);
        $response = $this->addSecurityHeaders($response);

        return $headOnly ? $response->withoutBody() : $response;
    }

    private function addSecurityHeaders(Response $response): Response
    {
        return $response
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('X-XSS-Protection', '1; mode=block')
            ->withHeader(
                'Content-Security-Policy',
                "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'"
            );
    }

    private function dispatchToRoute(Request $request, Route $route): mixed
    {
        $action = $route->action();
        $params = $this->app->router()->currentParameters();
        $context = ['request' => $request] + $params;

        if (is_callable($action)) {
            return $this->app->call($action, $context);
        }

        if (is_array($action) && count($action) === 2) {
            [$class, $method] = $action;
            return $this->app->call([$class, $method], $context);
        }

        return Response::html('Invalid route handler', 500);
    }

    private function normalizeResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        return Response::html((string) $result);
    }

    private function notFoundResponse(Request $request): Response
    {
        error_log(sprintf(
            '[%s] HTTP 404 Not Found | request=%s %s',
            date('Y-m-d H:i:s'),
            $request->method(),
            $request->path()
        ));

        try {
            return Response::html($this->app->view()->render('errors/404'), 404);
        } catch (\RuntimeException) {
            return Response::html('Not Found', 404);
        }
    }

    /**
     * @param array<int, string> $allowedMethods
     */
    private function methodNotAllowedResponse(array $allowedMethods, Request $request): Response
    {
        error_log(sprintf(
            '[%s] HTTP 405 Method Not Allowed | request=%s %s | allow=%s',
            date('Y-m-d H:i:s'),
            $request->method(),
            $request->path(),
            implode(', ', $allowedMethods)
        ));

        return Response::html('Method Not Allowed', 405, [
            'Allow' => implode(', ', $allowedMethods),
        ]);
    }
}
