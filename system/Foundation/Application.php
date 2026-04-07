<?php

declare(strict_types=1);

namespace System\Foundation;

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use System\Http\Kernel;
use System\Routing\Router;
use System\View\View;

class Application
{
    private static ?self $instance = null;

    private string $basePath;
    private Config $config;
    private Router $router;
    private View $view;
    private Kernel $kernel;

    /**
     * @var array<string, array{concrete: string|callable, shared: bool}>
     */
    private array $bindings = [];

    /**
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * @var array<string, array<int, class-string>>
     */
    private array $middlewareGroups = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        self::$instance = $this;
        $this->instance(self::class, $this);
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    public function basePath(string $path = ''): string
    {
        if ($path === '') {
            return $this->basePath;
        }

        return $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    public function setConfig(Config $config): void
    {
        $this->config = $config;
        $this->instance(Config::class, $config);
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function setRouter(Router $router): void
    {
        $this->router = $router;
        $this->instance(Router::class, $router);
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function setView(View $view): void
    {
        $this->view = $view;
        $this->instance(View::class, $view);
    }

    public function view(): View
    {
        return $this->view;
    }

    /**
     * @param array<int, class-string> $middlewares
     */
    public function setMiddlewareGroup(string $name, array $middlewares): void
    {
        $this->middlewareGroups[$name] = $middlewares;
    }

    /**
     * @return array<int, class-string>
     */
    public function middlewareGroup(string $name): array
    {
        return $this->middlewareGroups[$name] ?? [];
    }

    public function kernel(): Kernel
    {
        if (!isset($this->kernel)) {
            $this->kernel = new Kernel($this);
        }

        return $this->kernel;
    }

    /**
     * @param class-string $abstract
     * @param class-string|callable|null $concrete
     */
    public function bind(string $abstract, string|callable|null $concrete = null): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared' => false,
        ];
    }

    /**
     * @param class-string $abstract
     * @param class-string|callable|null $concrete
     */
    public function singleton(string $abstract, string|callable|null $concrete = null): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared' => true,
        ];
    }

    /**
     * @param class-string $abstract
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * @param class-string $abstract
     */
    public function make(string $abstract): object
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $binding = $this->bindings[$abstract] ?? null;
        if ($binding !== null) {
            $instance = $this->resolveConcrete($binding['concrete']);
            if ($binding['shared']) {
                $this->instances[$abstract] = $instance;
            }

            return $instance;
        }

        return $this->build($abstract);
    }

    public function call(callable|array $callable, array $parameters = []): mixed
    {
        $target = $callable;
        if (is_array($target) && isset($target[0], $target[1]) && is_string($target[0])) {
            $target[0] = $this->make($target[0]);
        }

        $reflection = $this->reflectCallable($target);
        $arguments = $this->resolveArguments($reflection, $parameters);

        return $target(...$arguments);
    }

    public function loadRoutesFrom(string $path, string $group): void
    {
        $router = $this->router();
        $router->setCurrentGroup($group);
        require $path;
        $router->setCurrentGroup(null);
    }

    private function resolveConcrete(string|callable $concrete): object
    {
        if (is_callable($concrete)) {
            $instance = $concrete($this);
            if (!is_object($instance)) {
                throw new \RuntimeException('Container factory must return an object.');
            }

            return $instance;
        }

        return $this->build($concrete);
    }

    /**
     * @param class-string $class
     */
    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new \RuntimeException('Class not found: ' . $class);
        }

        try {
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException $exception) {
            throw new \RuntimeException('Cannot reflect class: ' . $class, 0, $exception);
        }

        if (!$reflection->isInstantiable()) {
            throw new \RuntimeException('Class is not instantiable: ' . $class);
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $arguments = $this->resolveArguments($constructor, []);
        return $reflection->newInstanceArgs($arguments);
    }

    private function reflectCallable(callable|array $callable): ReflectionFunctionAbstract
    {
        if (is_array($callable)) {
            return new ReflectionMethod($callable[0], (string) $callable[1]);
        }

        if (is_string($callable)) {
            return new ReflectionMethod($callable, '__invoke');
        }

        return new ReflectionFunction($callable);
    }

    /**
     * @param array<int|string, mixed> $parameters
     * @return array<int, mixed>
     */
    private function resolveArguments(ReflectionFunctionAbstract $reflection, array $parameters): array
    {
        $named = [];
        $positionals = [];
        foreach ($parameters as $key => $value) {
            if (is_string($key)) {
                $named[$key] = $value;
                continue;
            }

            $positionals[] = $value;
        }

        $resolved = [];
        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $named)) {
                $resolved[] = $named[$name];
                continue;
            }

            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                $matched = $this->findMatchingObject($named, $className);
                if ($matched !== null) {
                    $resolved[] = $matched;
                    continue;
                }

                $resolved[] = $this->make($className);
                continue;
            }

            if ($positionals !== []) {
                $resolved[] = array_shift($positionals);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $resolved[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $resolved[] = null;
                continue;
            }

            throw new \RuntimeException(sprintf(
                'Unable to resolve parameter $%s for %s.',
                $name,
                $reflection->getName()
            ));
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function findMatchingObject(array $parameters, string $className): ?object
    {
        foreach ($parameters as $value) {
            if (is_object($value) && is_a($value, $className)) {
                return $value;
            }
        }

        return null;
    }
}
