<?php

declare(strict_types=1);

namespace System\Http;

class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $request
     * @param array<string, string> $headers
     * @param array<string, mixed> $server
     */
    public function __construct(
        private string $method,
        private string $uri,
        private array $query = [],
        private array $request = [],
        private array $headers = [],
        private array $server = []
    ) {
        $this->method = strtoupper($this->method);
    }

    public static function capture(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (!is_array($headers)) {
            $headers = [];
        }

        return new self($method, $uri, $_GET, $_POST, $headers, $_SERVER);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function create(string $method, string $uri, array $data = [], array $headers = []): self
    {
        $query = [];
        $request = [];
        if (strtoupper($method) === 'GET') {
            $query = $data;
        } else {
            $request = $data;
        }

        return new self($method, $uri, $query, $request, $headers, ['REQUEST_URI' => $uri]);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        $normalizedUri = '/' . ltrim($this->uri, '/');
        $path = parse_url($normalizedUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }

        return $path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->request);
    }

    public function header(string $key, ?string $default = null): ?string
    {
        foreach ($this->headers as $name => $value) {
            if (strtolower($name) === strtolower($key)) {
                return (string) $value;
            }
        }

        return $default;
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? null;
        return $https === 'on' || $https === '1';
    }
}
